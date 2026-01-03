<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to guard routes based on feature flags.
 *
 * Usage: ->middleware('feature:announcements')
 *
 * Returns 503 Service Unavailable if the feature is disabled.
 * Unknown features default to allowed (fail-safe) to prevent typos from breaking app.
 */
class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $feature  The feature flag name (e.g., 'announcements')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Read feature flag from config; default to true (fail-safe for unknown features)
        $isEnabled = config("features.{$feature}", true);

        if (!$isEnabled) {
            // Return 503 Service Unavailable
            return response(
                'Fitur sedang maintenance. Silakan coba lagi nanti.',
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return $next($request);
    }
}
