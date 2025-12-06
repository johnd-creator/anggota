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
        $query = DB::table('user_sessions')->join('users','user_sessions.user_id','=','users.id')
            ->select('user_sessions.*','users.name','users.email')
            ->orderByDesc('last_activity');
        if ($user = $request->get('user')) $query->where('users.email','like','%'.$user.'%');
        $sessions = $query->paginate(20)->withQueryString();
        return Inertia::render('Admin/Sessions/Index', [ 'sessions' => $sessions ]);
    }

    public function revoke(Request $request)
    {
        $sid = (string) $request->input('session_id');
        if ($sid) {
            $revoked = cache()->get('revoked_sessions', []);
            if (!in_array($sid, $revoked, true)) { $revoked[] = $sid; cache()->put('revoked_sessions', $revoked, 3600); }
            DB::table('user_sessions')->where('session_id', $sid)->delete();
            \App\Models\ActivityLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'session_terminated',
                'subject_type' => \App\Models\User::class,
                'subject_id' => null,
                'payload' => ['session_id' => $sid],
            ]);
        }
        return back()->with('success','Sesi dihentikan');
    }
}

