<?php

namespace App\Policies;

use App\Models\FinanceCategory;
use App\Models\User;

class FinanceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin','bendahara']);
    }

    public function view(User $user, FinanceCategory $category): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('bendahara')) return (int)$user->organization_unit_id === (int)$category->organization_unit_id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin','bendahara']);
    }

    public function update(User $user, FinanceCategory $category): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('bendahara')) return (int)$user->organization_unit_id === (int)$category->organization_unit_id;
        return false;
    }

    public function delete(User $user, FinanceCategory $category): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('bendahara')) return (int)$user->organization_unit_id === (int)$category->organization_unit_id;
        return false;
    }
}

