<?php

namespace App\Policies;

use App\Models\MemberUpdateRequest;
use App\Models\User;

class MemberUpdateRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    public function view(User $user, MemberUpdateRequest $request): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $request->member?->organization_unit_id;
        }

        return false;
    }

    public function approve(User $user, MemberUpdateRequest $request): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $request->member?->organization_unit_id;
        }

        return false;
    }

    public function reject(User $user, MemberUpdateRequest $request): bool
    {
        return $this->approve($user, $request);
    }
}
