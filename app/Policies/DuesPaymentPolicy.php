<?php

namespace App\Policies;

use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\User;

class DuesPaymentPolicy
{
    /**
     * Determine if user can view any dues payments.
     * All authenticated users can view (for "Iuran Saya" page).
     * Actual data filtering is done in controllers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can view a specific dues payment.
     */
    public function view(User $user, DuesPayment $duesPayment): bool
    {
        // Global access (super_admin, admin_pusat, bendahara_pusat)
        if ($user->canViewGlobalScope()) {
            return true;
        }

        // Member can view their own dues
        if ($user->member_id && $user->member_id === $duesPayment->member_id) {
            return true;
        }

        // Bendahara/pengurus can view dues in their unit
        if ($user->hasRole(['bendahara', 'pengurus'])) {
            $unitId = $user->currentUnitId();

            return $unitId !== null && $unitId === $duesPayment->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can create dues payments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'bendahara', 'bendahara_pusat']);
    }

    /**
     * Determine if user can update a dues payment.
     */
    public function update(User $user, DuesPayment $duesPayment): bool
    {
        // Super admin can manage all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat: can only edit DPP transactions
        if ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            $dppOrg = $user->managedOrganization;

            return $dppOrg && $dppOrg->is_pusat && $duesPayment->organization_unit_id === $dppOrg->id;
        }

        if ($user->hasRole('bendahara')) {
            $unitId = $user->currentUnitId();

            return $unitId !== null && $unitId === $duesPayment->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can update dues for a specific unit.
     * Used when creating new payment records.
     */
    public function updateForUnit(User $user, int $unitId): bool
    {
        // Super admin can manage all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat: can only update DPP
        if ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            $dppOrg = $user->managedOrganization;

            return $dppOrg && $dppOrg->is_pusat && $unitId === $dppOrg->id;
        }

        if ($user->hasRole('bendahara')) {
            return $user->currentUnitId() === $unitId;
        }

        return false;
    }

    /**
     * Determine if user can update dues for a specific member.
     * Used for dues update operations.
     */
    public function updateForMember(User $user, Member $member): bool
    {
        // Super admin can manage all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // admin_pusat & bendahara_pusat: can only update DPP members (should not exist)
        if ($user->hasRole(['admin_pusat', 'bendahara_pusat'])) {
            $dppOrg = $user->managedOrganization;

            return $dppOrg && $dppOrg->is_pusat && $member->organization_unit_id === $dppOrg->id;
        }

        if ($user->hasRole('bendahara')) {
            $unitId = $user->currentUnitId();

            return $unitId !== null && $unitId === $member->organization_unit_id;
        }

        return false;
    }
}
