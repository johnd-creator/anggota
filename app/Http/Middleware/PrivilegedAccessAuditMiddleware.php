<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivilegedAccessAuditMiddleware
{
    public function __construct(protected AuditService $auditService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * Logs access attempts for privileged routes defined in config/audit.php.
     * The log is created BEFORE authorization, so even 403 attempts are recorded.
     * The status_code and duration_ms will be updated by AuditRequestMiddleware after response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        $privilegedRoutes = config('audit.privileged_routes', []);
        $event = $privilegedRoutes[$routeName] ?? null;

        if (!$event) {
            return $next($request);
        }

        // Build safe payload
        $payload = $this->buildPayload($request, $routeName);

        // Extract subject if route has a model binding
        $subject = $this->extractSubject($request);

        // Log the access attempt (status_code will be updated post-response by AuditRequestMiddleware)
        $this->auditService->log($event, $payload, $subject);

        return $next($request);
    }

    /**
     * Build a safe payload for the audit log.
     * Only includes whitelisted, non-sensitive fields.
     */
    protected function buildPayload(Request $request, string $routeName): array
    {
        $payload = [
            'action' => $this->getActionLabel($routeName),
            'route_name' => $routeName,
        ];

        // Add report type for report exports
        if (str_starts_with($routeName, 'reports.')) {
            $type = $request->route('type');
            if ($type && in_array($type, ['growth', 'mutations', 'documents'], true)) {
                $payload['report_type'] = $type;
            }
        }

        // Add format if specified
        $format = $request->query('format');
        if ($format && in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $payload['format'] = $format;
        }

        // Add filter summary (sanitized - only count, not values)
        $filters = $request->only(['unit_id', 'status', 'date_start', 'date_end', 'period']);
        if (!empty($filters)) {
            $payload['filters'] = array_keys(array_filter($filters));
        }

        return $payload;
    }

    /**
     * Extract a subject model from route parameters if available.
     */
    protected function extractSubject(Request $request): ?Model
    {
        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $param) {
            if ($param instanceof Model) {
                return $param;
            }
        }

        return null;
    }

    /**
     * Get a human-readable action label for the route.
     */
    protected function getActionLabel(string $routeName): string
    {
        $labels = [
            'reports.export' => 'report_exported',
            'admin.members.export' => 'members_exported',
            'admin.admin.mutations.export' => 'mutations_exported',
            'finance.categories.export' => 'finance_categories_exported',
            'finance.ledgers.export' => 'finance_ledgers_exported',
            'member.card.pdf' => 'member_card_downloaded',
            'letters.pdf' => 'letter_downloaded',
            'letters.attachments.show' => 'letter_attachment_downloaded',
            'admin.members.import' => 'members_imported',
            'admin.members.import.template' => 'import_template_downloaded',
            'audit-logs' => 'audit_logs_accessed',
            'admin.sessions.index' => 'sessions_viewed',
            'admin.activity-logs.index' => 'activity_logs_viewed',
        ];

        return $labels[$routeName] ?? str_replace(['.', '-'], '_', $routeName);
    }
}
