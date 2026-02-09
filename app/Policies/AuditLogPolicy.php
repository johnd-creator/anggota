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
     *
     * Updated: pengurus can view logs from their own unit
     */
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->canPengurusViewOwnUnit($user);
    }

    /**
     * Determine whether the user can view a specific audit log.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->canPengurusViewOwnUnit($user, $auditLog);
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
        if (! $user->hasRole('admin_unit')) {
            return false;
        }

        if ($auditLog === null) {
            return false;
        }

        // Must match organization unit
        $userUnitId = $user->currentUnitId();
        if (! $userUnitId || $auditLog->organization_unit_id !== $userUnitId) {
            return false;
        }

        // Cannot view auth events
        if ($auditLog->event_category === 'auth') {
            return false;
        }

        return true;
    }

    /**
     * Check if pengurus can view logs from their own unit.
     *
     * Restrictions:
     * - Only events where organization_unit_id matches
     * - Can view auth events (unlike admin_unit)
     * - Read-only, no export
     */
    protected function canPengurusViewOwnUnit(User $user, ?AuditLog $auditLog = null): bool
    {
        if (! $user->hasRole('pengurus')) {
            return false;
        }

        // For viewAny, allow access (filtering will be done in query)
        if ($auditLog === null) {
            return true;
        }

        // For specific log, must match organization unit
        $userUnitId = $user->currentUnitId();
        if (! $userUnitId || $auditLog->organization_unit_id !== $userUnitId) {
            return false;
        }

        return true;
    }
}
