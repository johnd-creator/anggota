<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $dev = app()->environment('local') || app()->environment('development');
        $viteHosts = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];
        $wsHosts = [
            'ws://localhost:5173',
            'ws://127.0.0.1:5173',
        ];
        $viteHostsStr = implode(' ', $viteHosts);
        $wsHostsStr = implode(' ', $wsHosts);
        $scriptSrc = $dev ? "'self' 'unsafe-inline' 'unsafe-eval' {$viteHostsStr}" : "'self' 'unsafe-inline' 'unsafe-eval'";
        $styleSrc = $dev ? "'self' 'unsafe-inline' https://fonts.googleapis.com {$viteHostsStr}" : "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $connectSrc = $dev ? "'self' {$wsHostsStr} {$viteHostsStr}" : "'self'";
        $imgSrc = "'self' data: https://ui-avatars.com";
        $fontSrc = "'self' https://fonts.gstatic.com";
        $csp = "default-src 'self'; script-src {$scriptSrc}; style-src {$styleSrc}; font-src {$fontSrc}; img-src {$imgSrc}; connect-src {$connectSrc}";
        $response->headers->set('Content-Security-Policy', $csp);
        return $response;
    }
}
