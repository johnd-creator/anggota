<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered by controller/query scope
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Announcement $announcement): bool
    {
        // 1. Must be active
        if (!$announcement->is_active) {
            // Only creators or admins can see inactive
            if ($user->id === $announcement->created_by)
                return true;
            if ($this->hasAdminPrivileges($user))
                return true;
            return false;
        }

        // 2. Check scope
        if ($announcement->scope_type === 'global_all') {
            return true;
        }

        if ($announcement->scope_type === 'global_officers') {
            // Visible to officers (union_position != 'Anggota') and global admins
            return $user->isOfficer();
        }

        if ($announcement->scope_type === 'unit') {
            return $user->currentUnitId() === $announcement->organization_unit_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (!$user->role)
            return false;

        return in_array($user->role->name, ['super_admin', 'admin_pusat', 'admin_unit']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        // Super/Pusat can update anything
        if (in_array($user->role?->name, ['super_admin', 'admin_pusat'])) {
            return true;
        }

        // Admin Unit can only update their own unit's announcements
        if ($user->role?->name === 'admin_unit') {
            return $announcement->scope_type === 'unit' &&
                $announcement->organization_unit_id === $user->currentUnitId();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }

    private function hasAdminPrivileges(User $user): bool
    {
        return in_array($user->role?->name, ['super_admin', 'admin_pusat', 'admin_unit']);
    }
}
