<?php

namespace Lambda\Agent\Middleware;

use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class Customer
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
        config()->set('jwt.user', " Lambda\Agent\Models\Customer");
        config()->set('auth.providers.users.model', \Lambda\Agent\Models\Customer::class);
        return $next($request);
    }
}
