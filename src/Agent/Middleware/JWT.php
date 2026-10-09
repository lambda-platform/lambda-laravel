<?php

namespace Lambda\Agent\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JWT extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $token = null;
        if (isset($_COOKIE['token'])) {
            $token = str_replace('Bearer ', "", $_COOKIE['token']);
        }
        if ($request->header('Authorization')) {
            $token = str_replace('Bearer ', "", $request->header('Authorization'));
        }

        if ($token != null) {
            try {
                JWTAuth::setToken($token)->authenticate();
            } catch (\Exception $e) {
                if ($e instanceof TokenExpiredException) {
                    try {
                        $refreshed = JWTAuth::refresh(JWTAuth::getToken());
                        JWTAuth::setToken($refreshed)->toUser();
                        $request->headers->set('Authorization', 'Bearer ' . $refreshed);
                    } catch (JWTException $e) {
                        return response()->json([
                            'code' => 103,
                            'message' => 'Token cannot be refreshed, please Login again'
                        ]);
                    }
                } else {
                    // Invalid, blacklisted or otherwise unusable token
                    return $this->unauthorized();
                }
            }
        }
        if (auth() && auth()->user() && (in_array(auth()->user()->role, $roles)|| count($roles)==0)) {
            return $next($request);
        }

        return $this->unauthorized();
    }

    private function unauthorized($message = null){
        return redirect('/auth/login');
    }
}
