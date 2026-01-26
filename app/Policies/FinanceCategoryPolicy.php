<?php

namespace App\Policies;

use App\Models\FinanceCategory;
use App\Models\User;

class FinanceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'bendahara', 'pengurus']);
    }

    /**
     * View a category. Bendahara can view:
     * - Global categories (organization_unit_id = null)
     * - Categories in their own unit
     */
    public function view(User $user, FinanceCategory $category): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole(['bendahara', 'pengurus'])) {
            // Allow viewing global categories
            if ($category->organization_unit_id === null) {
                return true;
            }
            $unitId = $user->currentUnitId();

            return $unitId !== null && $unitId === $category->organization_unit_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'bendahara']);
    }

    public function update(User $user, FinanceCategory $category): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($user->hasRole('bendahara')) {
            // Cannot update global categories
            if ($category->organization_unit_id === null) {
                return false;
            }
            $unitId = $user->currentUnitId();

            return $unitId !== null && $unitId === $category->organization_unit_id;
        }

        return false;
    }

    public function delete(User $user, FinanceCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
