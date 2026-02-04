<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        // admin_pusat & bendahara_pusat can view all members
        if ($user->canViewGlobalScope()) {
            return true;
        }

        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'bendahara', 'bendahara_pusat', 'pengurus']);
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['admin_unit', 'bendahara', 'pengurus'])) {
            return $user->currentUnitId() === $member->organization_unit_id;
        }

        if ($user->hasRole('anggota')) {
            return $member->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Prevent DPP from registering members
        if ($user->managedOrganization?->is_pusat) {
            return false;
        }

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
        // Super admin can manage all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat: can only edit if member belongs to DPP
        if ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            return $member->organizationUnit?->is_pusat ?? false;
        }

        if ($user->hasRole(['admin_unit', 'bendahara'])) {
            return $user->currentUnitId() === $member->organization_unit_id;
        }

        return false;
    }

    public function delete(User $user, Member $member): bool
    {
        // Super admin can delete all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat: can only delete DPP members (should not exist anyway)
        if ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            return $member->organizationUnit?->is_pusat ?? false;
        }

        return false;
    }

    /**
     * Determine if user can export members.
     * Query will be scoped by controller for admin_unit/bendahara.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'bendahara']);
    }

    /**
     * Determine if user can import members.
     * admin_unit imports to their own unit only.
     */
    public function import(User $user): bool
    {
        // Prevent DPP from importing members
        if ($user->managedOrganization?->is_pusat) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin_unit']);
    }
}
