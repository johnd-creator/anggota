<?php

namespace App\Http\Controllers;

use App\Models\Aspiration;
use App\Models\AuditLog;
use App\Models\DuesPayment;
use App\Models\FinanceLedger;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\ExportScopeHelper;
use App\Services\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportsExportController extends Controller
{
    /**
     * Supported report types.
     * Types not fully implemented will return 501.
     */
    protected const IMPLEMENTED_TYPES = [
        'members',
        'aspirations',
        'dues_per_period',
        'dues_summary',
        'dues_audit',
        'finance_ledgers',
        'finance_monthly_summary'
    ];

    protected const ALLOWED_TYPES = [
        'members',
        'aspirations',
        'dues', // legacy check
        'finance_ledgers', // legacy check
        'dues_per_period',
        'dues_summary',
        'dues_audit',
        'finance_monthly_summary'
    ];

    /**
     * Export reports as CSV.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function export(Request $request, \App\Services\ReportExportStatus $statusService)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(self::ALLOWED_TYPES)],
            'unit_id' => 'nullable|integer',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'q' => 'nullable|string|max:80',
            // Members specific
            'union_position_id' => 'nullable|integer',
            'include_documents' => 'nullable',
            // Aspirations specific
            'include_member' => 'nullable',
            'include_user' => 'nullable',
            // Finance specific
            'period' => 'nullable|regex:/^\d{4}-\d{2}$/',
            'amount_default' => 'nullable|numeric',
            'include_notes' => 'nullable',
            'actor_user_id' => 'nullable|integer',
            'status_change' => 'nullable|string',
            'category_id' => 'nullable|integer',
            'include_attachment_url' => 'nullable',
            'year' => 'nullable|integer|min:2000|max:2100',
            'only_approved' => 'nullable',
            'ledger_type' => 'nullable|string|in:income,expense',
        ]);

        $user = $request->user();
        $type = $validated['type'];
        $requestedUnitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : null;

        // Check Finance Feature Flag
        $financeTypes = [
            'dues_per_period',
            'dues_summary',
            'dues_audit',
            'finance_ledgers',
            'finance_monthly_summary'
        ];
        if (in_array($type, $financeTypes) && !config('features.finance', true)) {
            return response()->json(['error' => 'Finance feature is disabled'], 503);
        }

        // Init status
        // Filter out sensitive data from meta
        $safeMeta = array_filter($validated, fn($k) => $k !== 'q', ARRAY_FILTER_USE_KEY);
        if (!empty($validated['q'])) {
            $safeMeta['q_len'] = strlen($validated['q']);
            $safeMeta['q_hash'] = hash('sha256', strtolower(trim($validated['q'])));
        }
        $statusService->start($user, $type, $safeMeta);

        try {
            // Enforce unit scope: non-global users are forced to their own unit
            $unitId = ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);
            $maskPii = ExportScopeHelper::shouldMaskPii($user);

            // Dispatch to appropriate handler
            return match ($type) {
                'members' => $this->exportMembers($user, $unitId, $maskPii, $validated, $statusService),
                'aspirations' => $this->exportAspirations($user, $unitId, $validated, $statusService),
                'dues_per_period' => $this->exportDuesPerPeriod($user, $unitId, $validated, $statusService),
                'dues_summary' => $this->exportDuesSummary($user, $unitId, $validated, $statusService),
                'dues_audit' => $this->exportDuesAudit($user, $unitId, $validated, $statusService),
                'finance_ledgers' => $this->exportFinanceLedgers($user, $unitId, $validated, $statusService),
                'finance_monthly_summary' => $this->exportFinanceMonthlySummary($user, $unitId, $validated, $statusService),
                default => $this->notImplemented($user, $type, $unitId, $validated, $statusService),
            };
        } catch (\Throwable $e) {
            $statusService->fail($user, $type, $e->getMessage());
            throw $e;
        }
    }

    // ... (exportMembers & exportAspirations kept same) ...

    /**
     * Export members report with enhanced filtering.
     */
    protected function exportMembers($user, ?int $unitId, bool $maskPii, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('export', Member::class);

        $query = Member::query()
            ->select([
                'id',
                'full_name',
                'email',
                'phone',
                'status',
                'organization_unit_id',
                'nra',
                'kta_number',
                'nip',
                'union_position_id',
                'join_date',
                'employee_id',
                'photo_path',
                'documents'
            ])
            ->with(['unit', 'unionPosition']);

        if ($unitId) {
            $query->where('organization_unit_id', $unitId);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['date_start'])) {
            $query->whereDate('join_date', '>=', $params['date_start']);
        }
        if (!empty($params['date_end'])) {
            $query->whereDate('join_date', '<=', $params['date_end']);
        }
        if (!empty($params['union_position_id'])) {
            $query->where('union_position_id', $params['union_position_id']);
        }
        if (!empty($params['q'])) {
            $search = '%' . $params['q'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', $search)
                    ->orWhere('kta_number', 'LIKE', $search)
                    ->orWhere('nra', 'LIKE', $search)
                    ->orWhere('employee_id', 'LIKE', $search);
            });
        }

        $rowCount = (clone $query)->count();

        $safeFilters = array_filter([
            'status' => $params['status'] ?? null,
            'date_start' => $params['date_start'] ?? null,
            'date_end' => $params['date_end'] ?? null,
            'union_position_id' => $params['union_position_id'] ?? null,
        ]);
        if (!empty($params['q'])) {
            $safeFilters['q_len'] = strlen($params['q']);
            $safeFilters['q_hash'] = hash('sha256', strtolower(trim($params['q'])));
        }

        ExportScopeHelper::auditExport($user, 'reports.members', $unitId, $rowCount, $safeFilters);

        $includeDocuments = filter_var($params['include_documents'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $unitLabel = $unitId ? "unit{$unitId}" : 'global';
        $filename = "report_members_{$unitLabel}_" . now()->format('Ymd_His') . '.csv';

        $headers = [
            'ID',
            'Nama',
            'KTA',
            'NRA',
            'Status',
            'Unit',
            'Jabatan Serikat',
            'Join Date',
            'Email',
            'Telepon',
            'Employee ID',
            'NIP',
            'Has Photo',
            'Has Documents'
        ];

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($query, $maskPii, $statusService, $user, $filename) {
            $query->orderBy('id')->chunk(500, function ($rows) use (&$out, $maskPii) {
                foreach ($rows as $m) {
                    $email = $maskPii ? ExportScopeHelper::maskPii($m->email, 'email') : $m->email;
                    $phone = $maskPii ? ExportScopeHelper::maskPii($m->phone, 'phone') : $m->phone;
                    $nip = $maskPii ? ExportScopeHelper::maskPii($m->nip, 'nip') : $m->nip;
                    fputcsv($out, [
                        $m->id,
                        $m->full_name,
                        $m->kta_number,
                        $m->nra,
                        $m->status,
                        $m->unit?->name,
                        $m->unionPosition?->name,
                        $m->join_date,
                        $email,
                        $phone,
                        $m->employee_id,
                        $nip,
                        $m->photo_path ? 'YA' : 'TIDAK',
                        !empty($m->documents) ? 'YA' : 'TIDAK'
                    ]);
                }
            });
        });
    }

    /**
     * Export aspirations report.
     */
    protected function exportAspirations($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('export', Aspiration::class);

        $query = Aspiration::query()->with(['unit', 'category', 'member', 'user']);

        if ($unitId)
            $query->where('organization_unit_id', $unitId);
        if (!empty($params['status']))
            $query->where('status', $params['status']);
        if (!empty($params['date_start']))
            $query->whereDate('created_at', '>=', $params['date_start']);
        if (!empty($params['date_end']))
            $query->whereDate('created_at', '<=', $params['date_end']);

        if (!empty($params['q'])) {
            $search = '%' . $params['q'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', $search)->orWhere('body', 'LIKE', $search);
            });
        }

        $rowCount = (clone $query)->count();

        $safeFilters = array_filter([
            'status' => $params['status'] ?? null,
            'date_start' => $params['date_start'] ?? null,
            'date_end' => $params['date_end'] ?? null,
        ]);
        if (!empty($params['q'])) {
            $safeFilters['q_len'] = strlen($params['q']);
            $safeFilters['q_hash'] = hash('sha256', strtolower(trim($params['q'])));
        }

        ExportScopeHelper::auditExport($user, 'reports.aspirations', $unitId, $rowCount, $safeFilters);

        $includeMember = filter_var($params['include_member'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeUser = filter_var($params['include_user'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $unitLabel = $unitId ? "unit{$unitId}" : 'global';
        $filename = "report_aspirations_{$unitLabel}_" . now()->format('Ymd_His') . '.csv';

        $headers = [
            'ID',
            'Created At',
            'Status',
            'Unit',
            'Category',
            'Title',
            'Body Snippet',
            'Support Count',
            'Merged Into ID'
        ];
        if ($includeMember) {
            $headers[] = 'Member ID';
            $headers[] = 'Member Name';
        }
        if ($includeUser) {
            $headers[] = 'User ID';
            $headers[] = 'User Name';
        }

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($query, $includeMember, $includeUser, $statusService, $user, $filename) {
            $query->orderBy('id')->chunk(500, function ($rows) use (&$out, $includeMember, $includeUser) {
                foreach ($rows as $a) {
                    $bodySnippet = Str::limit(strip_tags($a->body ?? ''), 200, '...');
                    $row = [
                        $a->id,
                        $a->created_at?->format('Y-m-d H:i:s'),
                        $a->status,
                        $a->unit?->name,
                        $a->category?->name,
                        $a->title,
                        $bodySnippet,
                        $a->support_count ?? 0,
                        $a->merged_into_id
                    ];
                    if ($includeMember) {
                        $row[] = $a->member_id;
                        $row[] = $a->member?->full_name;
                    }
                    if ($includeUser) {
                        $row[] = $a->user_id;
                        $row[] = $a->user?->name;
                    }
                    fputcsv($out, $row);
                }
            });
        });
    }

    // === NEW FINANCE EXPORT METHODS ===

    protected function exportDuesPerPeriod($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('viewAny', DuesPayment::class);

        $period = $params['period'] ?? now()->format('Y-m');
        $status = $params['status'] ?? null;
        $search = $params['q'] ?? null;

        $query = Member::query()
            ->select([
                'members.id',
                'members.full_name',
                'members.kta_number',
                'members.organization_unit_id',
                'organization_units.name as unit_name',
                'dues_payments.status as dues_status',
                'dues_payments.amount',
                'dues_payments.paid_at',
                'dues_payments.notes',
                'users.name as recorder_name'
            ])
            ->leftJoin('organization_units', 'members.organization_unit_id', '=', 'organization_units.id')
            ->leftJoin('dues_payments', function ($join) use ($period) {
                $join->on('members.id', '=', 'dues_payments.member_id')
                    ->where('dues_payments.period', '=', $period);
            })
            ->leftJoin('users', 'dues_payments.recorded_by', '=', 'users.id')
            ->where('members.status', 'aktif');

        if ($unitId) {
            $query->where('members.organization_unit_id', $unitId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('members.full_name', 'LIKE', "%{$search}%")
                    ->orWhere('members.kta_number', 'LIKE', "%{$search}%");
            });
        }
        if ($status === 'paid') {
            $query->where('dues_payments.status', 'paid');
        } elseif ($status === 'unpaid') {
            $query->where(function ($q) {
                $q->whereNull('dues_payments.status')->orWhere('dues_payments.status', 'unpaid');
            });
        }

        $rowCount = (clone $query)->count();

        $safeFilters = ['period' => $period, 'status' => $status, 'unit_id' => $unitId];
        if ($search) {
            $safeFilters['q_len'] = strlen($search);
            $safeFilters['q_hash'] = hash('sha256', strtolower(trim($search)));
        }
        $includeNotes = filter_var($params['include_notes'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $safeFilters['include_notes'] = $includeNotes;

        ExportScopeHelper::auditExport($user, 'reports.dues_per_period', $unitId, $rowCount, $safeFilters);

        $unitLabel = $unitId ? "unit{$unitId}" : 'global';
        $filename = "report_dues_{$period}_{$unitLabel}_" . now()->format('Ymd_His') . '.csv';

        $headers = ['Member ID', 'Full Name', 'KTA', 'Unit', 'Period', 'Status', 'Amount', 'Paid At', 'Recorded By'];
        if ($includeNotes)
            $headers[] = 'Notes';

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($query, $period, $includeNotes, $statusService, $user, $filename) {
            $query->orderBy('members.full_name')->chunk(500, function ($rows) use (&$out, $period, $includeNotes) {
                foreach ($rows as $r) {
                    $row = [
                        $r->id,
                        $r->full_name,
                        $r->kta_number,
                        $r->unit_name,
                        $period,
                        $r->dues_status ?? 'unpaid',
                        $r->amount,
                        $r->paid_at,
                        $r->recorder_name
                    ];
                    if ($includeNotes)
                        $row[] = $r->notes ?? '-';
                    fputcsv($out, $row);
                }
            });
        });
    }

    protected function exportDuesSummary($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('viewAny', DuesPayment::class);

        $period = $params['period'] ?? now()->format('Y-m');
        $defaultAmount = isset($params['amount_default']) ? (float) $params['amount_default'] : 30000;

        // Base query for units
        $unitsQuery = OrganizationUnit::query()->select('id', 'name');
        if ($unitId)
            $unitsQuery->where('id', $unitId);

        $units = $unitsQuery->orderBy('name')->get();

        $rowCount = count($units);
        ExportScopeHelper::auditExport($user, 'reports.dues_summary', $unitId, $rowCount, ['period' => $period]);

        $filename = "report_dues_summary_{$period}_" . now()->format('Ymd_His') . '.csv';
        $headers = ['Unit ID', 'Unit Name', 'Period', 'Total Members', 'Paid Count', 'Paid Amount', 'Unpaid Count', 'Unpaid Est. Amount'];

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($units, $period, $defaultAmount, $statusService, $user, $rowCount, $filename) {
            foreach ($units as $unit) {
                $totalMembers = Member::where('organization_unit_id', $unit->id)->where('status', 'aktif')->count();
                $paidData = DuesPayment::where('organization_unit_id', $unit->id)
                    ->where('period', $period)
                    ->where('status', 'paid')
                    ->selectRaw('count(*) as count, sum(amount) as total')
                    ->first();

                $paidCount = $paidData->count ?? 0;
                $paidAmount = $paidData->total ?? 0;
                $unpaidCount = max(0, $totalMembers - $paidCount);
                $unpaidEst = $unpaidCount * $defaultAmount;

                fputcsv($out, [
                    $unit->id,
                    $unit->name,
                    $period,
                    $totalMembers,
                    $paidCount,
                    $paidAmount,
                    $unpaidCount,
                    $unpaidEst
                ]);
            }
        });
    }

    protected function exportDuesAudit($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('viewAny', DuesPayment::class);

        $query = AuditLog::with(['user', 'organizationUnit'])
            ->whereIn('event', ['dues.mark_paid', 'dues.mark_unpaid', 'dues.batch_mark_paid']);

        if ($unitId) {
            $query->where('organization_unit_id', $unitId);
        }
        if (!empty($params['actor_user_id'])) {
            $query->where('user_id', $params['actor_user_id']);
        }
        if (!empty($params['date_start'])) {
            $query->whereDate('created_at', '>=', $params['date_start']);
        }
        if (!empty($params['date_end'])) {
            $query->whereDate('created_at', '<=', $params['date_end']);
        }

        $rowCount = (clone $query)->count();
        ExportScopeHelper::auditExport($user, 'reports.dues_audit', $unitId, $rowCount, $params);

        $filename = "report_dues_audit_" . now()->format('Ymd_His') . '.csv';
        $headers = ['Time', 'Event', 'Actor', 'Unit', 'Subject Internal ID', 'Period', 'Amount', 'Status Change'];

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($query, $statusService, $user, $filename) {
            $query->orderByDesc('created_at')->chunk(500, function ($rows) use (&$out) {
                foreach ($rows as $log) {
                    $payload = $log->payload;
                    $period = $payload['period'] ?? '-';
                    $amount = $payload['amount'] ?? '-';
                    $status = isset($payload['status_after']) ? "-> {$payload['status_after']}" : ($log->event);

                    fputcsv($out, [
                        $log->created_at,
                        $log->event,
                        $log->user->name ?? $log->user_id,
                        $log->organizationUnit->name ?? '-',
                        $log->subject_id ?? '-',
                        $period,
                        $amount,
                        $status
                    ]);
                }
            });
        });
    }

    protected function exportFinanceLedgers($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('viewAny', FinanceLedger::class);

        $query = FinanceLedger::query()->with(['category', 'organizationUnit', 'creator', 'approvedBy']);

        if ($unitId)
            $query->where('organization_unit_id', $unitId);
        if (!empty($params['ledger_type']))
            $query->where('type', $params['ledger_type']);
        if (!empty($params['status']))
            $query->where('status', $params['status']);
        if (!empty($params['category_id']))
            $query->where('finance_category_id', $params['category_id']);
        if (!empty($params['date_start']))
            $query->whereDate('date', '>=', $params['date_start']);
        if (!empty($params['date_end']))
            $query->whereDate('date', '<=', $params['date_end']);

        if (!empty($params['q'])) {
            $query->where('description', 'LIKE', '%' . $params['q'] . '%');
        }

        $rowCount = (clone $query)->count();
        $safeFilters = $params;
        if (!empty($params['q'])) {
            unset($safeFilters['q']);
            $safeFilters['q_len'] = strlen($params['q']);
            $safeFilters['q_hash'] = hash('sha256', strtolower(trim($params['q'])));
        }

        ExportScopeHelper::auditExport($user, 'reports.finance_ledgers', $unitId, $rowCount, $safeFilters);

        $includeUrl = filter_var($params['include_attachment_url'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $filename = "report_finance_ledgers_" . now()->format('Ymd_His') . '.csv';

        $headers = [
            'ID',
            'Date',
            'Unit',
            'Category',
            'Type',
            'Amount',
            'Status',
            'Description',
            'Created By',
            'Approved By',
            'Approved At'
        ];
        if ($includeUrl)
            $headers[] = 'Attachment URL';

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($query, $includeUrl, $statusService, $user, $filename) {
            $query->orderByDesc('date')->chunk(500, function ($rows) use (&$out, $includeUrl) {
                foreach ($rows as $l) {
                    $row = [
                        $l->id,
                        $l->date->format('Y-m-d'),
                        $l->organizationUnit->name ?? '-',
                        $l->category->name ?? '-',
                        $l->type,
                        $l->amount,
                        $l->status,
                        $l->description,
                        $l->creator->name ?? '-',
                        $l->approvedBy->name ?? '-',
                        $l->approved_at,
                    ];
                    if ($includeUrl)
                        $row[] = $l->attachment_path ? asset($l->attachment_path) : '-';
                    fputcsv($out, $row);
                }
            });
        });
    }

    protected function exportFinanceMonthlySummary($user, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        Gate::authorize('viewAny', FinanceLedger::class);

        $year = $params['year'] ?? now()->year;
        $onlyApproved = filter_var($params['only_approved'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Fetch ledgers for the year
        $query = FinanceLedger::query()
            ->whereYear('date', $year)
            ->with('organizationUnit');

        if ($unitId)
            $query->where('organization_unit_id', $unitId);
        if ($onlyApproved)
            $query->where('status', 'approved');

        // We can't do complex aggregation easily with Eloquent/DB raw across all DB types reliably 
        // without more complex logic, but here we can fetch and aggregate in PHP for reliability 
        // assuming dataset isn't millions of rows for one year. 
        // Better: Use SQLite specific date format strftime as requested, user OS is linux/sqlite/mysql.
        // Assuming SQLite based on past context or standard Laravel compat.
        // Let's use collection aggregation for safety and reusability.

        $ledgers = $query->get(); // In-memory aggregation might be heavy but safe for summary

        $summary = []; // [unit_id => [month => [income, expense]]]

        foreach ($ledgers as $ledger) {
            $uId = $ledger->organization_unit_id;
            $month = (int) $ledger->date->format('m');
            $idx = "{$uId}_{$month}";
            if (!isset($summary[$idx])) {
                $summary[$idx] = [
                    'unit_name' => $ledger->organizationUnit->name ?? 'Unknown',
                    'unit_id' => $uId,
                    'month' => $month,
                    'income' => 0,
                    'expense' => 0
                ];
            }
            if ($ledger->type === 'income')
                $summary[$idx]['income'] += $ledger->amount;
            else
                $summary[$idx]['expense'] += $ledger->amount;
        }

        // Sort keys to ensuring proper order if needed, but array_values is enough
        ksort($summary);

        $rowCount = count($summary);
        ExportScopeHelper::auditExport($user, 'reports.finance_monthly_summary', $unitId, $rowCount, ['year' => $year]);

        $filename = "report_finance_summary_{$year}_" . now()->format('Ymd_His') . '.csv';
        $headers = ['Unit ID', 'Unit Name', 'Month', 'Income', 'Expense', 'Net'];

        return ReportExporter::streamCsv($filename, $headers, function ($out) use ($summary, $statusService, $user, $rowCount, $filename) {
            foreach ($summary as $row) {
                fputcsv($out, [
                    $row['unit_id'],
                    $row['unit_name'],
                    $row['month'],
                    $row['income'],
                    $row['expense'],
                    $row['income'] - $row['expense']
                ]);
            }
            $statusService->complete($user, 'finance_monthly_summary', $rowCount, [], $filename);
        });
    }

    protected function notImplemented($user, string $type, ?int $unitId, array $params, \App\Services\ReportExportStatus $statusService)
    {
        ExportScopeHelper::auditExport($user, 'reports.' . $type, $unitId, 0, $params);
        $statusService->fail($user, $type, "Feature not implemented");
        return response()->json([
            'error' => 'Not implemented',
            'message' => "Report type '{$type}' is not yet implemented.",
        ], 501);
    }

    /**
     * Legacy per-type export (kept for backward compatibility).
     * Mapped from POST /reports/{type}/export
     */
    public function legacyExport(Request $request, string $type)
    {
        $user = Auth::user();
        $requestedUnitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;

        // Enforce unit scope: non-global users are forced to their own unit
        $unitId = ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);
        //$maskPii = ExportScopeHelper::shouldMaskPii($user); // Unused in this legacy block in web.php, but let's check

        if ($type === 'growth') {
            Gate::authorize('export', Member::class);

            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');
            $query = Member::query();
            if ($unitId)
                $query->where('organization_unit_id', $unitId);
            if ($dateStart)
                $query->whereDate('join_date', '>=', $dateStart);
            if ($dateEnd)
                $query->whereDate('join_date', '<=', $dateEnd);
            $filename = 'report_growth_' . now()->format('Ymd_His') . '.csv';
            ExportScopeHelper::auditExport($user, 'reports.growth', $unitId, 12, [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ]);
            return ReportExporter::streamCsv($filename, ['Month', 'Count'], function ($out) use ($query) {
                $months = collect(range(0, 11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
                $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))->groupBy('ym')->get()->keyBy('ym');
                foreach ($months as $m)
                    fputcsv($out, [$m, (int) optional($rows->get($m))->c]);
            });
        } elseif ($type === 'mutations') {
            Gate::authorize('viewAny', \App\Models\MutationRequest::class);

            $status = $request->input('status');
            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');
            $query = \App\Models\MutationRequest::query()->with(['member', 'fromUnit', 'toUnit']);
            if ($unitId)
                $query->where(function ($q) use ($unitId) {
                    $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
                });
            if ($status)
                $query->where('status', $status);
            if ($dateStart)
                $query->whereDate('effective_date', '>=', $dateStart);
            if ($dateEnd)
                $query->whereDate('effective_date', '<=', $dateEnd);
            $filename = 'report_mutations_' . now()->format('Ymd_His') . '.csv';
            $rowCount = (clone $query)->count();
            ExportScopeHelper::auditExport($user, 'reports.mutations', $unitId, $rowCount, [
                'status' => $status,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ]);
            return ReportExporter::streamCsv($filename, ['ID', 'Anggota', 'Asal', 'Tujuan', 'Status', 'Tanggal Efektif'], function ($out) use ($query) {
                $query->orderBy('id')->chunk(500, function ($rows) use (&$out) {
                    foreach ($rows as $r)
                        fputcsv($out, [$r->id, optional($r->member)->full_name, optional($r->fromUnit)->name, optional($r->toUnit)->name, $r->status, $r->effective_date]);
                });
            });
        }

        return response()->json(['error' => 'Unknown report'], 404);
    }

    /**
     * Admin Members Export.
     * Mapped from GET /admin/members-export
     */
    public function adminMembersExport(Request $request)
    {
        Gate::authorize('export', Member::class);

        $user = Auth::user();
        $requestedUnitId = $request->filled('unit_id') ? (int) $request->query('unit_id') : null;

        // Enforce unit scope: non-global users are forced to their own unit
        $unitId = ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);
        $maskPii = ExportScopeHelper::shouldMaskPii($user);

        $query = Member::query()->select(['id', 'full_name', 'email', 'phone', 'status', 'organization_unit_id', 'nra', 'kta_number', 'nip', 'union_position_id', 'join_date'])->with(['unit', 'unionPosition']);
        if ($unitId)
            $query->where('organization_unit_id', $unitId);
        $rowCount = (clone $query)->count();
        $filename = 'members_export_' . now()->format('Ymd_His') . '.csv';
        Cache::put('export:members:' . $user->id, ['status' => 'started', 'time' => now()->toISOString()], 300);
        ExportScopeHelper::auditExport($user, 'members', $unitId, $rowCount);
        return response()->streamDownload(function () use ($query, $user, $unitId, $maskPii) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nama', 'Email', 'Telepon', 'Status', 'Unit', 'NRA', 'KTA', 'NIP', 'Jabatan Serikat', 'Join Date']);
            $count = 0;
            $query->orderBy('id')->chunk(500, function ($rows) use (&$out, &$count, $maskPii) {
                foreach ($rows as $m) {
                    $email = $maskPii ? ExportScopeHelper::maskPii($m->email, 'email') : $m->email;
                    $phone = $maskPii ? ExportScopeHelper::maskPii($m->phone, 'phone') : $m->phone;
                    $nip = $maskPii ? ExportScopeHelper::maskPii($m->nip, 'nip') : $m->nip;
                    fputcsv($out, [$m->id, $m->full_name, $email, $phone, $m->status, $m->unit?->name, $m->nra, $m->kta_number, $nip, optional($m->unionPosition)->name, $m->join_date]);
                    $count++;
                }
            });
            Cache::put('export:members:' . $user->id, ['status' => 'completed', 'count' => $count, 'time' => now()->toISOString()], 300);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Admin Mutations Export.
     * Mapped from GET /admin/mutations/export
     */
    public function adminMutationsExport(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\MutationRequest::class);

        $user = Auth::user();
        $requestedUnitId = $request->filled('unit_id') ? (int) $request->query('unit_id') : null;

        // Enforce unit scope: non-global users are forced to their own unit
        $unitId = ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);

        $query = \App\Models\MutationRequest::query()->select(['id', 'member_id', 'from_unit_id', 'to_unit_id', 'status', 'effective_date'])->with(['member', 'fromUnit', 'toUnit']);
        if ($unitId)
            $query->where(function ($q) use ($unitId) {
                $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
            });
        $rowCount = (clone $query)->count();
        $filename = 'mutations_export_' . now()->format('Ymd_His') . '.csv';
        ExportScopeHelper::auditExport($user, 'mutations', $unitId, $rowCount);
        return response()->streamDownload(function () use ($query, $user, $unitId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Anggota', 'Asal', 'Tujuan', 'Status', 'Tanggal Efektif']);
            $count = 0;
            $query->orderBy('id')->chunk(500, function ($rows) use (&$out, &$count) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->id, optional($r->member)->full_name, optional($r->fromUnit)->name, optional($r->toUnit)->name, $r->status, $r->effective_date]);
                    $count++;
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
