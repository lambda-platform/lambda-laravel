<?php

namespace Lambda\Agent\Middleware;

use Closure;

class DomainIpCheck
{

    function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function handle($request, Closure $next)
    {
        $blockedHosts = ['anikill.win'];
        $blockedIps = ['202.9.40.186', '2a01:4f8:e0:1560::2'];
        $requestHost = parse_url($request->headers->get('origin'), PHP_URL_HOST);
        $requestIp = $this->getRealIpAddr();

//        $ref = $request->headers->get('referer');
//        if($ref != 'https://animax.mn' || $ref != 'https://www.animax.mn' || $ref != 'https://m.animax.mn'){
//            return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
//        }

        if (in_array($requestHost, $blockedHosts) || in_array($requestIp, $blockedIps)) {
            return response()->json(['status' => false, 'error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

//    public function terminate($request, $response)
//    {
//        $user = auth()->user();
//        if ($user) {
//            $response->headers->set('X-Username', auth()->id());
//        }
//    }
}
