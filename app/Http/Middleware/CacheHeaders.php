<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CacheHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $cacheType = 'default'): Response
    {
        $response = $next($request);

        // Don't cache if user is authenticated (except for specific routes)
        $isAuthenticated = Auth::check();

        return $this->addCacheHeaders($response, $cacheType, $isAuthenticated);
    }

    /**
     * Add appropriate cache headers based on cache type
     */
    private function addCacheHeaders(Response $response, string $cacheType, bool $isAuthenticated): Response
    {
        // Don't cache authenticated requests (security)
        if ($isAuthenticated && ! in_array($cacheType, ['public_api', 'assets'])) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        }

        switch ($cacheType) {
            case 'assets':
                // Cache static assets for 1 day
                $response->headers->set('Cache-Control', 'public, max-age=86400, immutable');
                break;

            case 'public_api':
                // Cache public API responses for 5 minutes
                $response->headers->set('Cache-Control', 'public, max-age=300, s-maxage=300');
                $response->headers->set('Vary', 'Accept-Encoding');
                break;

            case 'dashboard':
                // Cache dashboard data for 2 minutes (short, but helps with rapid navigation)
                $response->headers->set('Cache-Control', 'private, max-age=120');
                break;

            case 'reports':
                // Cache reports for 15 minutes
                $response->headers->set('Cache-Control', 'private, max-age=900');
                break;

            case 'no_cache':
                // Explicitly disable caching
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
                break;

            default:
                // Default: cache for 5 minutes if not authenticated
                if (! $isAuthenticated) {
                    $response->headers->set('Cache-Control', 'public, max-age=300');
                } else {
                    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                }
                break;
        }

        return $response;
    }
}
