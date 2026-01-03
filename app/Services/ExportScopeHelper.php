<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Helper service for export unit scope enforcement.
 */
class ExportScopeHelper
{
    /**
     * Get the effective unit ID for export operations.
     * Non-global users are forced to their own unit (ignores request param).
     */
    public static function getEffectiveUnitId(User $user, ?int $requestedUnitId): ?int
    {
        if ($user->hasGlobalAccess()) {
            return $requestedUnitId; // Global users can filter by any unit
        }

        // Non-global users are forced to their own unit
        return $user->currentUnitId();
    }

    /**
     * Check if user can export for a given unit.
     */
    public static function canExportForUnit(User $user, ?int $unitId): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        return $user->currentUnitId() === $unitId;
    }

    /**
     * Check if PII should be masked for this user.
     * Global users (super_admin, admin_pusat) see full PII.
     */
    public static function shouldMaskPii(User $user): bool
    {
        return !$user->hasGlobalAccess();
    }

    /**
     * Mask PII field based on type.
     */
    public static function maskPii(?string $value, string $type = 'email'): ?string
    {
        if (empty($value)) {
            return $value;
        }

        switch ($type) {
            case 'email':
                return self::maskEmail($value);
            case 'phone':
                return self::maskPhone($value);
            case 'nip':
                return self::maskNip($value);
            default:
                return $value;
        }
    }

    /**
     * Mask email: jo***@domain.com
     */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = strlen($local) > 2
            ? substr($local, 0, 2) . '***'
            : '***';
        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask phone: 08****5678 (keep first 2 and last 4)
     */
    private static function maskPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($clean);
        if ($len < 6) {
            return '******';
        }
        // Keep first 2 and last 4, always use 4 stars in the middle for consistency.
        return substr($clean, 0, 2) . '****' . substr($clean, -4);
    }

    /**
     * Mask NIP: ****5678 (keep last 4)
     */
    private static function maskNip(string $nip): string
    {
        $len = strlen($nip);
        if ($len <= 4) {
            return '****';
        }
        return str_repeat('*', $len - 4) . substr($nip, -4);
    }

    /**
     * Log export audit event.
     */
    public static function auditExport(User $user, string $type, ?int $unitId, int $rowCount, array $filters = []): void
    {
        if ($unitId === 0) {
            $unitId = null;
        }

        // Remove any PII from filters
        $safeFilters = array_filter($filters, function ($key) {
            return !in_array($key, ['email', 'phone', 'nip', 'password']);
        }, ARRAY_FILTER_USE_KEY);

        AuditLog::create([
            'user_id' => $user->id,
            'organization_unit_id' => $unitId,
            'event' => 'export.' . $type,
            'event_category' => 'export',
            'subject_type' => 'export',
            'subject_id' => null,
            'payload' => [
                'type' => $type,
                'unit_id' => $unitId,
                'row_count' => $rowCount,
                'filters' => $safeFilters,
            ],
        ]);
    }
}
