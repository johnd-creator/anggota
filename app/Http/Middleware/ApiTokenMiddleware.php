<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-API-Token') ?? $request->query('api_token');
        $expected = config('app.api_token');
        if (!$expected || $token !== $expected) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}

