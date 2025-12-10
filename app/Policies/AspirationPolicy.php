<?php

namespace App\Policies;

use App\Models\Aspiration;
use App\Models\User;

class AspirationPolicy
{
    /**
     * Member can view aspirations from their unit
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['anggota', 'bendahara', 'admin_unit', 'admin_pusat', 'super_admin']);
    }

    /**
     * Member can view aspiration if it's from their unit or they have global access
     */
    public function view(User $user, Aspiration $aspiration): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        // Admin unit can see their unit's aspirations
        if ($user->hasRole('admin_unit')) {
            return $user->organization_unit_id === $aspiration->organization_unit_id;
        }

        // Members can see aspirations from their member's unit
        $member = $user->member;
        return $member && $member->organization_unit_id === $aspiration->organization_unit_id;
    }

    /**
     * Only members and admins can create aspirations
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['anggota', 'bendahara', 'super_admin', 'admin_pusat', 'admin_unit']);
    }

    /**
     * Member can support aspiration if it's from their unit and not their own
     */
    public function support(User $user, Aspiration $aspiration): bool
    {
        // Global admins can support any (though weird, but allowed for testing/engagement)
        if ($user->hasGlobalAccess()) {
            return true;
        }

        $member = $user->member;
        $roleName = $user->role?->name;

        // If admin_unit without member profile, check unit match
        if (!$member && $roleName === 'admin_unit') {
            return $user->organization_unit_id === $aspiration->organization_unit_id;
        }

        if (!$member) {
            return false;
        }

        // Must be same unit
        if ($member->organization_unit_id !== $aspiration->organization_unit_id) {
            return false;
        }

        // Cannot support own aspiration
        if ($aspiration->member_id === $member->id) {
            return false;
        }

        // Cannot support merged aspirations
        if ($aspiration->isMerged()) {
            return false;
        }

        return true;
    }

    /**
     * Admin can update status (admin_unit for their unit, global admins for all)
     */
    public function update(User $user, Aspiration $aspiration): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->organization_unit_id === $aspiration->organization_unit_id;
        }

        return false;
    }

    /**
     * Admin can merge aspirations (same rules as update)
     */
    public function merge(User $user, Aspiration $aspiration): bool
    {
        return $this->update($user, $aspiration);
    }

    /**
     * Only super_admin can delete
     */
    public function delete(User $user, Aspiration $aspiration): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Admin can view any aspiration (for admin panel)
     */
    public function viewAnyAdmin(User $user): bool
    {
        return $user->hasRole(['admin_unit', 'admin_pusat', 'super_admin']);
    }
}
