<?php

namespace App\Policies;

use App\Models\DuesPayment;
use App\Models\User;

class DuesPaymentPolicy
{
    /**
     * Determine if user can view any dues payments
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit', 'bendahara']);
    }

    /**
     * Determine if user can view a specific dues payment
     */
    public function view(User $user, DuesPayment $duesPayment): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole(['admin_unit', 'bendahara'])) {
            return (int) $user->organization_unit_id === (int) $duesPayment->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can create dues payments
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'bendahara']);
    }

    /**
     * Determine if user can update a dues payment
     */
    public function update(User $user, DuesPayment $duesPayment): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('bendahara')) {
            return (int) $user->organization_unit_id === (int) $duesPayment->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can update dues for a specific unit
     * Used when creating new payment records
     */
    public function updateForUnit(User $user, int $unitId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('bendahara')) {
            return (int) $user->organization_unit_id === $unitId;
        }

        return false;
    }
}
