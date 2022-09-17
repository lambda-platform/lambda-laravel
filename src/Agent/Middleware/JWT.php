<?php

namespace Lambda\Agent\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
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
                if ($e instanceof TokenInvalidException) {
                    $status = 401;
                    $message = 'This token is invalid. Please Login';
                    return $next($request);
                    //return response()->json(compact('status', 'message'), 401);
                } else if ($e instanceof TokenExpiredException) {
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
                    $message = 'Authorization Token not found';
                    return response()->json(compact('message'), 401);
                }
            }
        }
        if (auth() && (in_array(auth()->user()->role, $roles)|| count($roles)==0)) {
            return $next($request);
        }

        return $this->unauthorized();
    }

    private function unauthorized($message = null){
        return response()->json([
            'message' => $message ? $message : 'Та энэ хэсэгт хандах боломжгүй байна',
            'success' => false
        ], 401);
    }
}
