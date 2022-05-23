<?php

namespace Lambda\Agent\Middleware;

use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class Admin
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
        config()->set('jwt.user', " Lambda\Agent\Models\User");
        config()->set('auth.providers.users.model', \Lambda\Agent\Models\User::class);
        return $next($request);
    }
}
