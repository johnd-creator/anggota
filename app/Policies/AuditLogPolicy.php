<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any audit logs.
     * 
     * MVP: super_admin only.
     * Phase 2 will add admin_unit with restrictions.
     */
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can view a specific audit log.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can export audit logs.
     */
    public function export(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can delete audit logs.
     * 
     * Nobody can manually delete audit logs - only the purge command.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Check if the user is a super_admin.
     */
    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Phase 2: Check if admin_unit can view logs for their unit.
     * 
     * Restrictions:
     * - Only events where organization_unit_id matches
     * - Exclude auth category (no login_failed cross-unit visibility)
     * - Payload PII fields masked
     * - Read-only, no export
     * 
     * @codeCoverageIgnore - Not used in MVP
     */
    protected function canAdminUnitViewOwnUnit(User $user, ?AuditLog $auditLog = null): bool
    {
        if (!$user->hasRole('admin_unit')) {
            return false;
        }

        if ($auditLog === null) {
            return false;
        }

        // Must match organization unit
        $userUnitId = $user->organization_unit_id ?? $user->member?->organization_unit_id;
        if (!$userUnitId || $auditLog->organization_unit_id !== $userUnitId) {
            return false;
        }

        // Cannot view auth events
        if ($auditLog->event_category === 'auth') {
            return false;
        }

        return true;
    }
}
