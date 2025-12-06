<?php

namespace App\Policies;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrganizationUnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->hasRole(['super_admin', 'admin_unit']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->hasRole('super_admin');
    }
}
