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
        if (request()->ajax() || request()->wantsJson()) {
            return $this->jwtLogin($credentials);
        }
    }

    public function jwtLogin($credentials)
    {
        try {
//            $token = JWTAuth::attempt($credentials, ['exp' => Carbon::now()->addWeek()->timestamp]);
            $token = auth('api')->attempt($credentials, ['exp' => Carbon::now()->addWeek()->timestamp]);
        } catch (JWTException $e) {
            return response()->json(['status' => false, 'error' => 'Could not authenticate', 'exception' => $e->getMessage()], 500);
        }

        if (!$token) {
            return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
        } else {
            $meta = $this->respondWithToken($token);
//            JWTAuth::parseToken()->authenticate();
            JWTAuth::setToken($token);
            setcookie("token", $token, time() + 31556926, '/', NULL, 0);
            return response()
                ->json([
                    'status' => true,
                    'data' => request()->user(),
                    'meta' => $meta,
                    'path' => $this->checkRole(auth()->user()->role),
                ], 200)
                ->header('Authotization', "bearer " . $token);
//                ->withCookie(cookie('token', $token, auth()->factory()->getTTL() * 86400));
        }
    }

    public function checkRole($role)
    {
        $config = Config::get('lambda');
        $roleRedirects = $config['role-redirects'];
//        $defaultRedirect = '/' . env('LAMBDA_APP_NAME', 'mle');
        $defaultRedirect = $config['app_url'];

        foreach ($roleRedirects as $roleRedirect) {
            if ($roleRedirect['role_id'] == $role) {
                return $roleRedirect['url'];
            }
        }

        if ($role != 1) {
            //quiz custom
            $user_group = DB::table('roles')->where('id', $role)->first();

            if ($user_group) {
                if ($user_group->permissions) {
                    $permissions = json_decode($user_group->permissions);
                    if ($permissions->default_menu) {
                        return $defaultRedirect . $permissions->default_menu;
                    } else {
                        return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
                    }
                } else {
                    return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
                }
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
        return $this->respondWithToken(auth()->refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }
}
