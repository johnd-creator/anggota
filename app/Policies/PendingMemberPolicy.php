<?php

namespace App\Policies;

use App\Models\PendingMember;
use App\Models\User;

class PendingMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    public function view(User $user, PendingMember $pending): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $pending->organization_unit_id;
        }

        return false;
    }

    public function approve(User $user, PendingMember $pending): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $pending->organization_unit_id;
        }

        return false;
    }

    public function reject(User $user, PendingMember $pending): bool
    {
        return $this->approve($user, $pending);
    }
}
