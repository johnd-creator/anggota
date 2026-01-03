<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'bendahara']);
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['admin_unit', 'bendahara'])) {
            return $user->currentUnitId() === $member->organization_unit_id;
        }

        if ($user->hasRole('anggota')) {
            return $member->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit']);
    }

    /**
     * Determine if user can create member in a specific unit.
     * admin_unit must create in their own unit only.
     */
    public function createInUnit(User $user, int $unitId): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $unitId;
        }

        return false;
    }

    public function update(User $user, Member $member): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['admin_unit', 'bendahara'])) {
            return $user->currentUnitId() === $member->organization_unit_id;
        }

        return false;
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine if user can export members.
     * Query will be scoped by controller for admin_unit.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    /**
     * Determine if user can import members.
     * admin_unit imports to their own unit only.
     */
    public function import(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit']);
    }
}
