<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('user_sessions')->join('users', 'user_sessions.user_id', '=', 'users.id')
            ->select('user_sessions.*', 'users.name', 'users.email')
            ->orderByDesc('last_activity');
        if ($user = $request->get('user'))
            $query->where('users.email', 'like', '%' . $user . '%');
        $sessions = $query->paginate(20)->withQueryString();
        return Inertia::render('Admin/Sessions/Index', ['sessions' => $sessions]);
    }

    public function revoke(Request $request)
    {
        $sid = (string) $request->input('session_id');
        if ($sid) {
            // Use individual cache key with short TTL (5 minutes)
            cache()->put('revoked_session:' . $sid, true, 300);

            \Log::info('Session revoked by admin', [
                'session_id' => $sid,
                'by_user_id' => $request->user()->id,
                'by_user_email' => $request->user()->email,
            ]);

            DB::table('user_sessions')->where('session_id', $sid)->delete();
            \App\Models\ActivityLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'session_terminated',
                'subject_type' => \App\Models\User::class,
                'subject_id' => null,
                'payload' => ['session_id' => $sid],
            ]);
        }
        return back()->with('success', 'Sesi dihentikan');
    }
}

