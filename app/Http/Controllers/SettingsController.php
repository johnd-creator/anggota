<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display settings page.
     */
    public function index()
    {
        $user = Auth::user();
        $pref = \App\Models\NotificationPreference::where('user_id', $user?->id)->first();
        $profile = [
            'name' => $user?->member?->full_name ?? $user?->name,
            'email' => $user?->email,
        ];
        $canQuickActions = $user?->role?->name === 'super_admin';

        return Inertia::render('Settings/Index', [
            'notification_prefs' => $pref ? [
                'channels' => $pref->channels,
                'digest_daily' => (bool) $pref->digest_daily,
                'updated_at' => $pref->updated_at,
            ] : null,
            'profile' => $profile,
            'can_quick_actions' => $canQuickActions,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'channels' => ['required', 'array'],
            'channels.mutations' => ['array'],
            'channels.updates' => ['array'],
            'channels.onboarding' => ['array'],
            'channels.security' => ['array'],
            'channels.letters' => ['nullable', 'boolean'], // Legacy bool support
            // New Categories
            'channels.announcements' => ['array'],
            'channels.dues' => ['array'],
            'channels.reports' => ['array'],
            'channels.finance' => ['array'],

            'digest_daily' => ['boolean'],
        ]);

        $pref = \App\Models\NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['channels' => $data['channels'], 'digest_daily' => (bool) ($data['digest_daily'] ?? false), 'updated_at' => now()]
        );

        return response()->json(['status' => 'ok', 'updated_at' => $pref->updated_at?->toISOString()]);
    }

    /**
     * Update profile name.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $oldName = $user->name;

        DB::transaction(function () use ($user, $data, $oldName) {
            // Update User
            $user->name = $data['name'];
            $user->save();

            // Update Member if exists
            if ($user->member) {
                $user->member->full_name = $data['name'];
                $user->member->save();
            }

            // Audit Log
            \App\Models\ActivityLog::create([
                'actor_id' => $user->id,
                'action' => 'profile_update',
                'subject_type' => \App\Models\User::class,
                'subject_id' => $user->id,
                'payload' => ['old_name' => $oldName, 'new_name' => $data['name']],
                'event_category' => 'system',
            ]);
        });

        return response()->json(['status' => 'ok', 'updated_at' => now()->toISOString()]);
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        DB::transaction(function () use ($user, $data) {
            $user->password = Hash::make($data['password']);
            $user->save();

            \App\Models\ActivityLog::create([
                'actor_id' => $user->id,
                'action' => 'password_changed',
                'subject_type' => \App\Models\User::class,
                'subject_id' => $user->id,
                'payload' => ['via' => 'settings'],
                'event_category' => 'security',
            ]);
        });

        Auth::logoutOtherDevices($data['password']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Get active sessions.
     */
    public function getSessions(Request $request)
    {
        $userId = Auth::id();
        $currentId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderBy('last_activity', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($s) use ($currentId) {
                return [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'is_current_device' => $s->id === $currentId,
                    'last_activity' => \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                ];
            });

        return response()->json(['sessions' => $sessions]);
    }
}
