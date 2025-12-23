<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestMiddleware
{
    public function __construct(protected AuditService $auditService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * Captures request context for audit logging:
     * - request_id (from RequestIdMiddleware)
     * - session_id
     * - route_name
     * - http_method
     * - url_path
     * - ip_address
     * - user_agent
     * - duration_ms (set after response)
     * - status_code (set after response)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Capture initial request context
        $context = [
            'request_id' => $request->headers->get('X-Request-ID'),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'route_name' => $request->route()?->getName(),
            'http_method' => $request->method(),
            'url_path' => $this->sanitizeUrlPath($request->path()),
            'ip_address' => $request->ip(),
            'user_agent' => $this->truncateUserAgent($request->userAgent()),
            'status_code' => null,
            'duration_ms' => null,
        ];

        $this->auditService->setRequestContext($context);

        $response = $next($request);

        // Calculate duration and capture status code
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $statusCode = $response->getStatusCode();

        // Update context for any future logs (unlikely but possible)
        $this->auditService->setStatusCode($statusCode);
        $this->auditService->setDuration($durationMs);

        // Update any audit logs created during this request with final status_code and duration_ms
        $this->auditService->updatePendingLogs($statusCode, $durationMs);

        return $response;
    }

    /**
     * Sanitize URL path to prevent logging sensitive route parameters.
     * 
     * Masks potentially sensitive path segments like tokens, IDs in certain contexts.
     */
    protected function sanitizeUrlPath(string $path): string
    {
        // Limit path length
        if (strlen($path) > 255) {
            $path = substr($path, 0, 252) . '...';
        }

        // Mask UUIDs in paths (often used as tokens)
        $path = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '[UUID]',
            $path
        );

        // Mask long alphanumeric strings that might be tokens (32+ chars)
        $path = preg_replace(
            '/\/[a-zA-Z0-9]{32,}/',
            '/[TOKEN]',
            $path
        );

        return $path;
    }

    /**
     * Truncate user agent to reasonable length.
     */
    protected function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        if (strlen($userAgent) > 255) {
            return substr($userAgent, 0, 252) . '...';
        }

        return $userAgent;
    }
}
