<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    public function view(User $user, User $target): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            $unitId = $user->currentUnitId();
            if (!$unitId) {
                return false;
            }

            $targetUnitId = $target->organization_unit_id ?: $target->linkedMember?->organization_unit_id;
            return $targetUnitId !== null && (int) $targetUnitId === (int) $unitId;
        }

        return false;
    }
}

