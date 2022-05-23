<?php

namespace Lambda\Agent\Middleware;

use Closure;
use http\Exception;
use Illuminate\Session\TokenMismatchException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Token;

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
    public function handle($request, Closure $next)
    {
        if ($_COOKIE['token']) {
            $token = str_replace('Bearer ', "", $_COOKIE['token']);
            try {
                JWTAuth::setToken($token)->authenticate();
            } catch (Exception $e) {
                if ($e instanceof TokenInvalidException) {
                    $status = 401;
                    $message = 'This token is invalid. Please Login';
                    return response()->json(compact('status', 'message'), 401);
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
                } else { q
                    $message = 'Authorization Token not found';
                    return response()->json(compact('message'), 404);
                }
            }
        }
        return $next($request);
    }
}
