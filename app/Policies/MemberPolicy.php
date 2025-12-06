<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('admin_unit')) return true; // unit scoping will be enforced at controller/query level
        if ($user->hasRole('anggota')) return $member->user_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin','admin_unit']);
    }

    public function update(User $user, Member $member): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('admin_unit')) return true; // unit scoping enforced in controller
        return false;
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->hasRole('super_admin');
    }
}
