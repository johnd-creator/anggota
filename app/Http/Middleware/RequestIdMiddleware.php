<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $rid = $request->headers->get('X-Request-ID') ?: (string) Str::uuid();
        $request->headers->set('X-Request-ID', $rid);
        Log::withContext(['request_id' => $rid]);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $rid);
        return $response;
    }
}

