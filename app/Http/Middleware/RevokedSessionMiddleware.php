<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

class RevokedSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $sid = Session::getId();
        $user = Auth::user();

        // Super admin bypass
        if ($user && $user->role && $user->role->name === 'super_admin') {
            return $next($request);
        }

        // Check individual cache key (short TTL) instead of shared array
        $revokeKey = 'revoked_session:' . $sid;
        if (cache()->has($revokeKey)) {
            // Log the force logout for traceability
            \Log::warning('Force logout triggered by RevokedSessionMiddleware', [
                'session_id' => $sid,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Clean up the cache key after revoke
            cache()->forget($revokeKey);

            return redirect()->route('login')->with('error', 'Sesi Anda telah dihentikan oleh admin');
        }

        return $next($request);
    }
}
