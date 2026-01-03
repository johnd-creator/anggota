<?php

namespace App\Policies;

use App\Models\FinanceLedger;
use App\Models\User;

class FinanceLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit', 'bendahara']);
    }

    public function view(User $user, FinanceLedger $ledger): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['admin_unit', 'bendahara'])) {
            $unitId = $user->currentUnitId();
            return $unitId !== null && $unitId === $ledger->organization_unit_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'bendahara']);
    }

    public function update(User $user, FinanceLedger $ledger): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('bendahara')) {
            // Bendahara cannot edit approved/rejected ledgers when workflow is enabled
            if (FinanceLedger::workflowEnabled() && in_array($ledger->status, ['approved', 'rejected'])) {
                return false;
            }
            $unitId = $user->currentUnitId();
            return $unitId !== null
                && $unitId === $ledger->organization_unit_id
                && (int) $ledger->created_by === (int) $user->id;
        }

        return false;
    }

    public function delete(User $user, FinanceLedger $ledger): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('bendahara')) {
            // Bendahara cannot delete approved/rejected ledgers when workflow is enabled
            if (FinanceLedger::workflowEnabled() && in_array($ledger->status, ['approved', 'rejected'])) {
                return false;
            }
            $unitId = $user->currentUnitId();
            return $unitId !== null
                && $unitId === $ledger->organization_unit_id
                && (int) $ledger->created_by === (int) $user->id;
        }

        return false;
    }

    /**
     * Determine if user can approve a ledger.
     * Only admin_unit can approve ledgers in their unit.
     */
    public function approve(User $user, FinanceLedger $ledger): bool
    {
        if (!FinanceLedger::workflowEnabled()) {
            return false;
        }

        if ($ledger->status !== 'submitted') {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('admin_unit')) {
            $unitId = $user->currentUnitId();
            return $unitId !== null && $unitId === $ledger->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine if user can reject a ledger.
     * Only admin_unit can reject ledgers in their unit.
     */
    public function reject(User $user, FinanceLedger $ledger): bool
    {
        return $this->approve($user, $ledger);
    }
}
