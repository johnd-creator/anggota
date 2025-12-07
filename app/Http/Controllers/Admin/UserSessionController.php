<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Notifications\SessionTerminatedNotification;

class UserSessionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', UserSession::class);

        $query = UserSession::with(['user.role']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->role;
            $query->whereHas('user.role', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('date_start')) {
            $query->whereDate('last_activity', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('last_activity', '<=', $request->date_end);
        }

        $sessions = $query->latest('last_activity')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Sessions/Index', [
            'sessions' => $sessions,
            'filters' => $request->only(['search', 'role', 'date_start', 'date_end']),
        ]);
    }

    public function destroy(UserSession $session)
    {
        Gate::authorize('delete', $session);

        // Notify user
        if ($session->user) {
            try {
                $session->user->notify(new SessionTerminatedNotification('Sesi dihentikan manual oleh admin.'));
            } catch (\Throwable $e) {
                // Ignore if notification fails
            }
        }

        // Delete from Laravel's sessions table if driver is database
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('id', $session->session_id)->delete();
        }

        $session->delete();

        return back()->with('success', 'Sesi berhasil dihentikan.');
    }

    public function destroyUserSessions(User $user)
    {
        Gate::authorize('deleteForUser', [UserSession::class, $user]);

        // Notify user
        try {
            $user->notify(new SessionTerminatedNotification('Semua sesi Anda dihentikan oleh admin.'));
        } catch (\Throwable $e) {
            // Ignore
        }

        // Delete from Laravel's sessions table if driver is database
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        
        // Also delete by session_ids found in user_sessions (in case user_id in sessions table is null for some reason, though unlikely if logged in)
        $sessionIds = UserSession::where('user_id', $user->id)->pluck('session_id');
        if (config('session.driver') === 'database' && $sessionIds->isNotEmpty()) {
             DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }

        UserSession::where('user_id', $user->id)->delete();

        return back()->with('success', 'Semua sesi user berhasil dihentikan.');
    }
}
