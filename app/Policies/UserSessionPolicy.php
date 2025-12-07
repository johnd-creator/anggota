<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, UserSession $userSession)
    {
        return $user->hasRole('super_admin');
    }

    public function deleteForUser(User $user, User $targetUser)
    {
        return $user->hasRole('super_admin');
    }
}
