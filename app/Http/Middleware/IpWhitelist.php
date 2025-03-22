<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedFromWhiteList = env('IP_WHITELIST') ?? [];

        if (!in_array($request->ip(), $allowedFromWhiteList)) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
