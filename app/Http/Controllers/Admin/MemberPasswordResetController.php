<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class MemberPasswordResetController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function edit(Member $member)
    {
        $member->load(['unit', 'user']);

        Gate::authorize('resetPassword', $member);

        return Inertia::render('Admin/Members/ResetPassword', [
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'kta_number' => $member->kta_number,
                'organization_unit_id' => $member->organization_unit_id,
                'unit' => $member->unit ? [
                    'id' => $member->unit->id,
                    'name' => $member->unit->name,
                    'code' => $member->unit->code,
                ] : null,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'has_google_sso' => (bool) $member->user->google_id,
                    'has_microsoft_sso' => (bool) $member->user->microsoft_id,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $member->load(['unit', 'user']);

        Gate::authorize('resetPassword', $member);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $targetUser = $member->user;
        if (! $targetUser) {
            return back()->withErrors([
                'password' => 'Anggota belum memiliki akun user yang terhubung.',
            ]);
        }

        DB::transaction(function () use ($request, $member, $targetUser, $data) {
            $targetUser->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();

            $sessionsQuery = DB::table('sessions')->where('user_id', $targetUser->id);
            if ($request->user()->id === $targetUser->id) {
                $sessionsQuery->where('id', '!=', $request->session()->getId());
            }

            $revokedSessionCount = $sessionsQuery->delete();
            $revokedTokenCount = $targetUser->tokens()->delete();

            $payload = [
                'via' => 'admin_member_master',
                'target_user_id' => $targetUser->id,
                'target_member_id' => $member->id,
                'target_member_kta' => $member->kta_number,
                'organization_unit_id' => $member->organization_unit_id,
                'sessions_revoked' => $revokedSessionCount,
                'tokens_revoked' => $revokedTokenCount,
                'has_google_sso' => (bool) $targetUser->google_id,
                'has_microsoft_sso' => (bool) $targetUser->microsoft_id,
            ];

            $this->auditService->log(
                'member_password_reset',
                $payload,
                $targetUser,
                $request->user()->id,
                $member->organization_unit_id
            );
        });

        return redirect()
            ->route('admin.members.show', $member)
            ->with('success', 'Password anggota berhasil direset. Login Google SSO tetap aktif.');
    }
}
