<?php

namespace App\Policies;

use App\Models\PendingMember;
use App\Models\User;

class PendingMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }

    public function view(User $user, PendingMember $pending): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }

    public function approve(User $user, PendingMember $pending): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }

    public function reject(User $user, PendingMember $pending): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }
}

