<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Use Gate for policy-based authorization
        Gate::authorize('viewAny', \App\Models\AuditLog::class);

        $filters = $request->only(['role', 'date_start', 'date_end', 'category', 'event', 'unit_id', 'request_id']);

        $query = \App\Models\AuditLog::with(['user.role', 'organizationUnit'])->filter($filters);

        // Pengurus can only see logs from their own unit
        if (Auth::user()->hasRole('pengurus')) {
            $unitId = Auth::user()->currentUnitId();
            if ($unitId) {
                $query->where('organization_unit_id', $unitId);
            }
        }

        $logs = $query->latest()
            ->paginate(20)
            ->withQueryString();

        // Access logging handled by PrivilegedAccessAuditMiddleware

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => ['auth', 'member', 'mutation', 'surat', 'iuran', 'export', 'system'],
        ]);
    }
}
