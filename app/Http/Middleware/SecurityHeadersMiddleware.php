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
        $isDev = app()->isLocal() || app()->environment('development');

        $viteHosts = $isDev ? "http://localhost:5173 http://127.0.0.1:5173" : "";
        $wsHosts = $isDev ? "ws://localhost:5173 ws://127.0.0.1:5173" : "";

        $scriptSrc = "'self' https://static.cloudflareinsights.com";
        $styleSrc = "'self' https://fonts.googleapis.com";
        $connectSrc = "'self' https://cloudflareinsights.com";
        $imgSrc = "'self' data: blob: https://ui-avatars.com https://*.googleusercontent.com";
        $fontSrc = "'self' https://fonts.gstatic.com";

        if ($isDev) {
            $scriptSrc .= " 'unsafe-inline' 'unsafe-eval' {$viteHosts}";
            $styleSrc .= " 'unsafe-inline' {$viteHosts}";
            $connectSrc .= " {$wsHosts} {$viteHosts}";
            $imgSrc .= " {$viteHosts}";
        } else {
            // Production strictness (optional: add 'unsafe-inline' if needed for Vue/Inertia still)
            $scriptSrc .= " 'unsafe-inline' 'unsafe-eval'";
            $styleSrc .= " 'unsafe-inline'";
        }

        $csp = "default-src 'self'; script-src {$scriptSrc}; style-src {$styleSrc}; font-src {$fontSrc}; img-src {$imgSrc}; connect-src {$connectSrc}";
        $response->headers->set('Content-Security-Policy', $csp);
        return $response;
    }
}
