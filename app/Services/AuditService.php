<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Request context captured by middleware.
     */
    protected array $requestContext = [];

    /**
     * IDs of audit logs created during this request (for post-response update).
     */
    protected array $createdLogIds = [];

    /**
     * Set request context from middleware.
     */
    public function setRequestContext(array $context): void
    {
        $this->requestContext = $context;
    }

    /**
     * Get the current request context.
     */
    public function getRequestContext(): array
    {
        return $this->requestContext;
    }

    /**
     * Log an audit event.
     *
     * @param string $event Event name (e.g., 'login_success', 'member.created')
     * @param array $payload Additional data to log
     * @param Model|null $subject The entity this event relates to
     * @param int|null $userId Override the user ID (default: current auth user)
     * @param int|null $organizationUnitId Override the organization unit ID
     */
    public function log(
        string $event,
        array $payload = [],
        ?Model $subject = null,
        ?int $userId = null,
        ?int $organizationUnitId = null
    ): AuditLog {
        // Determine event category
        $category = $this->resolveCategory($event);

        // Sanitize payload
        $sanitizedPayload = $this->sanitizePayload($payload, $category);

        // Resolve user and organization unit
        $user = Auth::user();
        $resolvedUserId = $userId ?? $user?->id;
        $resolvedUnitId = $organizationUnitId ?? $this->resolveOrganizationUnitId($user, $subject);

        // Build the log entry
        $logData = [
            'request_id' => $this->requestContext['request_id'] ?? null,
            'session_id' => $this->requestContext['session_id'] ?? null,
            'user_id' => $resolvedUserId,
            'organization_unit_id' => $resolvedUnitId,
            'event' => $event,
            'event_category' => $category,
            'route_name' => $this->requestContext['route_name'] ?? null,
            'http_method' => $this->requestContext['http_method'] ?? null,
            'url_path' => $this->requestContext['url_path'] ?? null,
            'status_code' => $this->requestContext['status_code'] ?? null,
            'duration_ms' => $this->requestContext['duration_ms'] ?? null,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->requestContext['ip_address'] ?? request()->ip(),
            'user_agent' => $this->requestContext['user_agent'] ?? request()->userAgent(),
            'payload' => $sanitizedPayload,
        ];

        $auditLog = AuditLog::create($logData);

        // Track this log ID for post-response update
        $this->createdLogIds[] = $auditLog->id;

        // Dual-write to activity_logs if enabled
        if (config('audit.dual_write', true) && $category !== 'auth' && $category !== 'auth_failed') {
            $this->dualWriteToActivityLog($event, $sanitizedPayload, $subject, $resolvedUserId);
        }

        return $auditLog;
    }

    /**
     * Log an authentication event (convenience method).
     */
    public function logAuth(string $event, array $payload = [], ?int $userId = null): AuditLog
    {
        return $this->log($event, $payload, null, $userId);
    }

    /**
     * Log an entity event (convenience method).
     */
    public function logEntity(string $event, Model $subject, array $payload = []): AuditLog
    {
        return $this->log($event, $payload, $subject);
    }

    /**
     * Resolve event category from event name.
     */
    protected function resolveCategory(string $event): string
    {
        $categories = config('audit.categories', []);

        foreach ($categories as $prefix => $category) {
            if (Str::startsWith($event, $prefix)) {
                return $category;
            }
        }

        return 'system';
    }

    /**
     * Sanitize payload by removing forbidden fields and applying whitelist.
     */
    protected function sanitizePayload(array $payload, string $category): array
    {
        // First, strip forbidden fields
        $forbidden = config('audit.forbidden_fields', []);
        $payload = $this->stripFields($payload, $forbidden);

        // Then apply whitelist for the category
        $whitelist = config('audit.payload_whitelist.' . $category, []);

        if (empty($whitelist)) {
            // If no whitelist defined, only strip forbidden and return
            return $payload;
        }

        return $this->applyWhitelist($payload, $whitelist);
    }

    /**
     * Recursively strip forbidden fields from payload.
     */
    protected function stripFields(array $data, array $forbidden): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Check if key matches any forbidden pattern
            $lowerKey = Str::lower($key);
            $isForbidden = false;

            foreach ($forbidden as $forbiddenKey) {
                if ($lowerKey === Str::lower($forbiddenKey) || Str::contains($lowerKey, Str::lower($forbiddenKey))) {
                    $isForbidden = true;
                    break;
                }
            }

            if ($isForbidden) {
                continue;
            }

            // Recursively process nested arrays
            if (is_array($value)) {
                $value = $this->stripFields($value, $forbidden);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Apply whitelist to payload, keeping only allowed fields.
     */
    protected function applyWhitelist(array $data, array $whitelist): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $whitelist, true)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Resolve organization unit ID from user or subject.
     */
    protected function resolveOrganizationUnitId($user, ?Model $subject): ?int
    {
        // Try to get from subject first
        if ($subject && method_exists($subject, 'getAttribute')) {
            if ($unitId = $subject->getAttribute('organization_unit_id')) {
                return (int) $unitId;
            }
        }

        // Try to get from user's member record or direct attribute
        if ($user) {
            if ($unitId = $user->organization_unit_id ?? null) {
                return (int) $unitId;
            }

            // Try via member relationship
            if (method_exists($user, 'member') && $user->member) {
                if ($unitId = $user->member->organization_unit_id ?? null) {
                    return (int) $unitId;
                }
            }
        }

        return null;
    }

    /**
     * Dual-write to activity_logs for backward compatibility.
     */
    protected function dualWriteToActivityLog(
        string $event,
        array $payload,
        ?Model $subject,
        ?int $userId
    ): void {
        try {
            ActivityLog::create([
                'actor_id' => $userId,
                'action' => $event,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // Silently fail dual-write to avoid breaking main flow
            \Log::warning('Audit dual-write to activity_logs failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update status code after response is generated.
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->requestContext['status_code'] = $statusCode;
    }

    /**
     * Update duration after response is generated.
     */
    public function setDuration(int $durationMs): void
    {
        $this->requestContext['duration_ms'] = $durationMs;
    }

    /**
     * Get IDs of audit logs created during this request.
     */
    public function getCreatedLogIds(): array
    {
        return $this->createdLogIds;
    }

    /**
     * Update all pending audit logs with status_code and duration_ms.
     * Called by middleware after response is generated.
     */
    public function updatePendingLogs(int $statusCode, int $durationMs): void
    {
        if (empty($this->createdLogIds)) {
            return;
        }

        try {
            AuditLog::whereIn('id', $this->createdLogIds)
                ->whereNull('status_code')
                ->update([
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs,
                ]);
        } catch (\Throwable $e) {
            // Silently fail to avoid breaking response
            \Log::warning('Failed to update audit log status_code/duration_ms', [
                'ids' => $this->createdLogIds,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear tracked IDs
        $this->createdLogIds = [];
    }
}
