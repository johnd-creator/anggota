<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user() || !$request->user()->role) {
            return redirect()->route('login');
        }

        if (in_array($request->user()->role->name, $roles)) {
            return $next($request);
        }

        // Fallback for Reguler users trying to access restricted pages
        if ($request->user()->role->name === 'reguler') {
            return redirect()->route('itworks');
        }

        abort(403, 'Unauthorized');
    }
}
