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
        $revoked = cache()->get('revoked_sessions', []);
        if (in_array($sid, $revoked, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Sesi Anda telah dihentikan oleh admin');
        }
        return $next($request);
    }
}

