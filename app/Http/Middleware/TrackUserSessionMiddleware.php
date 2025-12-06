<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;

class TrackUserSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (app()->environment('testing')) {
            return $response;
        }
        $user = Auth::user();
        if ($user) {
            $sid = Session::getId();
            try {
                if (Schema::hasTable('user_sessions')) {
                    DB::table('user_sessions')->updateOrInsert(
                        ['user_id' => $user->id, 'session_id' => $sid],
                        ['ip' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'last_activity' => now(), 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('TrackUserSession failed', [
                    'userId' => $user->id,
                    'request_id' => $request->headers->get('X-Request-Id'),
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $response;
    }
}
