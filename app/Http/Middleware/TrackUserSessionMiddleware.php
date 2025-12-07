<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\UserSession;

class TrackUserSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = Auth::user();
        if ($user) {
            $sid = Session::getId();
            try {
                UserSession::updateOrCreate(
                    ['user_id' => $user->id, 'session_id' => $sid],
                    [
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255), // Truncate if too long
                        'last_activity' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                // Fail silently to not break the app
                \Log::warning('TrackUserSession failed', [
                    'userId' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $response;
    }
}
