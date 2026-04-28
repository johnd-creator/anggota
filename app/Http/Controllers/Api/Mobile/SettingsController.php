<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $oldName = $user->name;

        DB::transaction(function () use ($user, $data, $oldName) {
            $user->forceFill(['name' => $data['name']])->save();

            if ($user->linkedMember) {
                $user->linkedMember->forceFill(['full_name' => $data['name']])->save();
            } elseif ($user->member) {
                $user->member->forceFill(['full_name' => $data['name']])->save();
            }

            ActivityLog::create([
                'actor_id' => $user->id,
                'action' => 'profile_update',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'payload' => ['old_name' => $oldName, 'new_name' => $data['name'], 'channel' => 'mobile'],
                'event_category' => 'system',
            ]);
        });

        return response()->json(['status' => 'ok', 'updated_at' => now()->toISOString()]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();

            ActivityLog::create([
                'actor_id' => $user->id,
                'action' => 'password_changed',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'payload' => ['via' => 'mobile_settings'],
                'event_category' => 'security',
            ]);
        });

        $currentTokenId = $request->user()?->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json(['status' => 'ok']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'is_current_device' => $request->user()->currentAccessToken()?->id === $token->id,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at?->toISOString(),
            ]);

        return response()->json(['sessions' => $tokens]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $query = $request->user()->tokens();

        if ($currentTokenId) {
            $query->where('id', '!=', $currentTokenId);
        }

        $count = $query->delete();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'revoke_other_mobile_tokens',
            'subject_type' => User::class,
            'subject_id' => $request->user()->id,
            'payload' => ['count' => $count],
            'event_category' => 'auth',
        ]);

        return response()->json(['status' => 'ok', 'count' => $count]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channels' => ['required', 'array'],
            'channels.mutations' => ['array'],
            'channels.updates' => ['array'],
            'channels.onboarding' => ['array'],
            'channels.security' => ['array'],
            'channels.letters' => ['nullable'],
            'channels.announcements' => ['array'],
            'channels.dues' => ['array'],
            'channels.reports' => ['array'],
            'channels.finance' => ['array'],
            'digest_daily' => ['boolean'],
        ]);

        $pref = NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'channels' => $data['channels'],
                'digest_daily' => (bool) ($data['digest_daily'] ?? false),
            ]
        );

        return response()->json([
            'status' => 'ok',
            'notification_prefs' => [
                'channels' => $pref->channels,
                'digest_daily' => (bool) $pref->digest_daily,
                'updated_at' => $pref->updated_at?->toISOString(),
            ],
        ]);
    }
}
