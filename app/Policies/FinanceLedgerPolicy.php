<?php

namespace App\Policies;

use App\Models\FinanceLedger;
use App\Models\User;

class FinanceLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'admin_unit', 'bendahara', 'bendahara_pusat', 'pengurus', 'pengurus_pusat']);
    }

    public function view(User $user, FinanceLedger $ledger): bool
    {
        return $user->canAccessFinanceUnit($ledger->organization_unit_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_pusat', 'bendahara', 'bendahara_pusat']);
    }

    public function update(User $user, FinanceLedger $ledger): bool
    {
        if ($user->hasRole('bendahara')) {
            if (FinanceLedger::workflowEnabled() && in_array($ledger->status, ['approved', 'rejected'])) {
                return false;
            }

            return $user->canManageFinanceUnit($ledger->organization_unit_id)
                && (int) $ledger->created_by === (int) $user->id;
        }

        return $user->canManageFinanceUnit($ledger->organization_unit_id);
    }

    public function delete(User $user, FinanceLedger $ledger): bool
    {
        if ($user->hasRole('bendahara')) {
            if (FinanceLedger::workflowEnabled() && in_array($ledger->status, ['approved', 'rejected'])) {
                return false;
            }

            return $user->canManageFinanceUnit($ledger->organization_unit_id)
                && (int) $ledger->created_by === (int) $user->id;
        }

        return $user->canManageFinanceUnit($ledger->organization_unit_id);
    }

    /**
     * Determine if user can approve a ledger.
     * Only admin_unit can approve ledgers in their unit.
     */
    public function approve(User $user, FinanceLedger $ledger): bool
    {
        if (! FinanceLedger::workflowEnabled()) {
            return false;
        }

        if ($ledger->status !== 'submitted') {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['bendahara', 'bendahara_pusat'])) {
            return $user->canManageFinanceUnit($ledger->organization_unit_id);
        }

        if ($user->hasRole(['admin_unit', 'admin_pusat', 'pengurus_pusat'])) {
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
