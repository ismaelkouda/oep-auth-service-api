<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // OWASP REST Security Cheat Sheet. HSTS relève du point qui termine le TLS (la passerelle).
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
