<?php

namespace Lambda\Agent\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt', ['except' => ['login', 'asyncLogin']]);
    }

    public function login()
    {
        //Returning login page
        if (request()->isMethod('get')) {
            if (auth()->user() && auth()->user()->role) {
                $path = $this->checkRole(auth()->user()->role);
                if ($path) {
                    return redirect()->to($path);
                }
            }
            return view('agent::login');
        }

        //Validating
        $credentials = request()->only('login', 'password');
        $validator = Validator::make($credentials, [
            'login' => 'required|string|max:255',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'error' => $validator->errors()]);
        }

        //JWT Auth
        return $this->jwtLogin($credentials);
    }

    public function jwtLogin($credentials)
    {
        try {
            $config = Config::get('lambda');
            if (isset($config['user_login_check_active']) && $config['user_login_check_active'] == 1) {
                $user = DB::table("users")->where('login', $credentials['login'])->first();
                if (!$user) {
                    return response()->json(['status' => false, 'error' => 'User Not found'], 401);
                }
                if ($user->is_active == null || $user->is_active == 0) {
                    return response()->json(['status' => false, 'error' => 'Хэрэглэгч баталгаажаагүй байна'], 401);
                }
            }
            $ttl = config('jwt.ttl', 60);
            JWTAuth::factory()->setTTL($ttl);
            $token = auth('api')->attempt($credentials);
        } catch (JWTException $e) {
            report($e);
            return response()->json(['status' => false, 'error' => 'Could not authenticate'], 500);
        }

        if (!$token) {
            return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
        }

        $path = $this->checkRole(auth('api')->user()->role);
        if (!$path) {
            auth('api')->logout();
            return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
        }

        return response()
            ->json([
                'status' => true,
                'path' => $path,
            ], 200)
            ->header('Authorization', 'Bearer ' . $token)
            ->withCookie('token', $token, $ttl, '/');
    }

    public function checkRole($role)
    {
        $config = Config::get('lambda');
        $roleRedirects = $config['role-redirects'] ?? [];
        $defaultRedirect = $config['app_url'] ?? '/';

        foreach ($roleRedirects as $roleRedirect) {
            if ($roleRedirect['role_id'] == $role) {
                return $roleRedirect['url'];
            }
        }

        if ($role != 1) {
            //quiz custom
            $user_group = DB::table('roles')->where('id', $role)->first();

            if ($user_group) {
                $permissions = $user_group->permissions ? json_decode($user_group->permissions) : null;
                // null means the role has no landing page, callers treat it as unauthorized
                return !empty($permissions->default_menu) ? $defaultRedirect . $permissions->default_menu : null;
            }
        }
        return $defaultRedirect;
    }

    public function logout()
    {
        auth()->logout();
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['message' => 'Successfully logged out']);
        }
        return redirect()->to('auth/login');
    }

    public function refresh()
    {
        $token = auth('api')->refresh();
        $ttl = config('jwt.ttl', 60);

        return response()
            ->json(['status' => true, 'token' => $token])
            ->header('Authorization', 'Bearer ' . $token)
            ->withCookie('token', $token, $ttl, '/');
    }

    public function me()
    {
        return response()->json(auth()->user());
    }
}
