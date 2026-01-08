<?php

namespace App\Policies;

use App\Models\MutationRequest;
use App\Models\User;

class MutationRequestPolicy
{
    /**
     * Determine if user can view any mutation requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    /**
     * Determine if user can view a specific mutation request.
     * admin_unit must be involved (from_unit or to_unit).
     */
    public function view(User $user, MutationRequest $mutation): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $this->isInvolvedUnit($user, $mutation);
        }

        return false;
    }

    /**
     * Determine if user can create mutation requests.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit']);
    }

    /**
     * Determine if user can create a mutation for a specific member.
     * admin_unit can only mutate members from their own unit.
     */
    public function createFor(User $user, \App\Models\Member $member): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $user->currentUnitId() === $member->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can approve a mutation request.
     * Only global roles can approve, and only when status is pending.
     */
    public function approve(User $user, MutationRequest $mutation): bool
    {
        if ($mutation->status !== 'pending') {
            return false;
        }

        return $user->hasGlobalAccess();
    }

    /**
     * Determine if user can reject a mutation request.
     */
    public function reject(User $user, MutationRequest $mutation): bool
    {
        return $this->approve($user, $mutation);
    }

    /**
     * Determine if user can cancel a mutation request.
     * Global roles can cancel any; admin_unit can cancel only their unit's mutations.
     */
    public function cancel(User $user, MutationRequest $mutation): bool
    {
        if ($mutation->status !== 'pending') {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            return $this->isInvolvedUnit($user, $mutation);
        }

        return false;
    }

    /**
     * Check if user's unit is involved (from or to) in the mutation.
     */
    protected function isInvolvedUnit(User $user, MutationRequest $mutation): bool
    {
        $unitId = $user->currentUnitId();
        if (!$unitId) {
            return false;
        }

        return $unitId === $mutation->from_unit_id || $unitId === $mutation->to_unit_id;
    }
}
