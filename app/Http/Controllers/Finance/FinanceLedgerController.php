<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceLedger;
use App\Models\FinanceCategory;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Notifications\FinanceLedgerApprovedNotification;
use App\Notifications\FinanceLedgerRejectedNotification;

class FinanceLedgerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FinanceLedger::class);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $isAdminUnit = $user && $user->role && $user->role->name === 'admin_unit';
        $workflowEnabled = FinanceLedger::workflowEnabled();

        $query = FinanceLedger::query()->with(['category', 'organizationUnit', 'creator', 'approvedBy']);

        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $categoryId = $request->query('category_id');
        $type = $request->query('type');
        $status = $request->query('status');
        $search = $request->query('search');
        $unitParam = $request->query('unit_id');

        if (!$isSuper) {
            $query->where('organization_unit_id', $user->organization_unit_id);
        } else {
            if ($unitParam)
                $query->where('organization_unit_id', (int) $unitParam);
        }

        if ($dateStart)
            $query->whereDate('date', '>=', $dateStart);
        if ($dateEnd)
            $query->whereDate('date', '<=', $dateEnd);
        if ($categoryId)
            $query->where('finance_category_id', (int) $categoryId);
        if ($type && in_array($type, ['income', 'expense']))
            $query->where('type', $type);
        if ($status && in_array($status, ['draft', 'submitted', 'approved', 'rejected']))
            $query->where('status', $status);
        if ($search)
            $query->where('description', 'like', "%{$search}%");

        $ledgers = $query->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        $units = $isSuper ? OrganizationUnit::select('id', 'name')->orderBy('name')->get() : [];
        $categories = FinanceCategory::select('id', 'name', 'type', 'organization_unit_id')
            ->when(!$isSuper, function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->whereNull('organization_unit_id')->orWhere('organization_unit_id', $user->organization_unit_id);
                });
            })
            ->orderBy('name')->get();

        // Saldo calculation - only approved if workflow enabled
        $saldoQuery = FinanceLedger::query();
        if (!$isSuper) {
            $saldoQuery->where('organization_unit_id', $user->organization_unit_id);
        } elseif ($unitParam) {
            $saldoQuery->where('organization_unit_id', (int) $unitParam);
        }
        if ($workflowEnabled) {
            $saldoQuery->where('status', 'approved');
        }

        $saldo = [
            'income' => (float) (clone $saldoQuery)->where('type', 'income')->sum('amount'),
            'expense' => (float) (clone $saldoQuery)->where('type', 'expense')->sum('amount'),
        ];
        $saldo['balance'] = $saldo['income'] - $saldo['expense'];

        // Monthly summary - only approved if workflow enabled
        $monthStart = now()->startOfMonth()->toDateString();
        $monthQuery = FinanceLedger::query()
            ->when(!$isSuper, fn($q) => $q->where('organization_unit_id', $user->organization_unit_id));
        if ($workflowEnabled) {
            $monthQuery->where('status', 'approved');
        }

        $monthIncome = (float) (clone $monthQuery)->where('type', 'income')->whereDate('date', '>=', $monthStart)->sum('amount');
        $monthExpense = (float) (clone $monthQuery)->where('type', 'expense')->whereDate('date', '>=', $monthStart)->sum('amount');

        // Count pending approvals for admin_unit
        $pendingCount = 0;
        if ($isAdminUnit && $workflowEnabled) {
            $pendingCount = FinanceLedger::where('organization_unit_id', $user->organization_unit_id)
                ->where('status', 'submitted')
                ->count();
        }

        return Inertia::render('Finance/Ledgers/Index', [
            'ledgers' => $ledgers,
            'filters' => $request->only(['date_start', 'date_end', 'category_id', 'type', 'status', 'search', 'unit_id']),
            'units' => $units,
            'categories' => $categories,
            'summary' => ['income' => $monthIncome, 'expense' => $monthExpense, 'balance' => $monthIncome - $monthExpense],
            'saldo' => $saldo,
            'workflowEnabled' => $workflowEnabled,
            'pendingCount' => $pendingCount,
            'canApprove' => $isAdminUnit,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', FinanceLedger::class);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $units = $isSuper ? OrganizationUnit::select('id', 'name')->orderBy('name')->get() : [];
        $categories = FinanceCategory::select('id', 'name', 'type', 'organization_unit_id')
            ->when(!$isSuper, function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->whereNull('organization_unit_id')->orWhere('organization_unit_id', $user->organization_unit_id);
                });
            })
            ->orderBy('name')->get();

        return Inertia::render('Finance/Ledgers/Form', [
            'units' => $units,
            'categories' => $categories,
            'ledger' => null,
            'workflowEnabled' => FinanceLedger::workflowEnabled(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', FinanceLedger::class);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $unitId = $isSuper ? (int) $request->input('organization_unit_id') : (int) $user->organization_unit_id;
        $workflowEnabled = FinanceLedger::workflowEnabled();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'finance_category_id' => ['required', 'integer', Rule::exists('finance_categories', 'id')],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'organization_unit_id' => [$isSuper ? 'required' : 'nullable'],
            'attachment' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        $category = FinanceCategory::findOrFail((int) $validated['finance_category_id']);
        if (!$isSuper) {
            if (!is_null($category->organization_unit_id) && (int) $category->organization_unit_id !== (int) $unitId) {
                return back()->withErrors(['finance_category_id' => 'Kategori tidak valid untuk unit']);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('finance/attachments', 'public');
        }

        // Default status based on workflow setting
        $defaultStatus = FinanceLedger::defaultStatus();
        $approvedBy = null;

        $ledger = FinanceLedger::create([
            'organization_unit_id' => $unitId,
            'finance_category_id' => (int) $validated['finance_category_id'],
            'type' => $validated['type'] ?? $category->type,
            'amount' => (float) $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => $defaultStatus,
            'approved_by' => $approvedBy,
            'created_by' => $user->id,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'ledger_created',
            'subject_type' => FinanceLedger::class,
            'subject_id' => $ledger->id,
            'payload' => [
                'amount' => $ledger->amount,
                'type' => $ledger->type,
                'status' => $ledger->status,
            ],
        ]);

        return redirect()->route('finance.ledgers.index')->with('success', 'Transaksi berhasil dibuat');
    }

    public function edit(FinanceLedger $ledger)
    {
        Gate::authorize('update', $ledger);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $units = $isSuper ? OrganizationUnit::select('id', 'name')->orderBy('name')->get() : [];
        $categories = FinanceCategory::select('id', 'name', 'type', 'organization_unit_id')
            ->when(!$isSuper, function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->whereNull('organization_unit_id')->orWhere('organization_unit_id', $user->organization_unit_id);
                });
            })
            ->orderBy('name')->get();

        return Inertia::render('Finance/Ledgers/Form', [
            'units' => $units,
            'categories' => $categories,
            'ledger' => $ledger->only(['id', 'organization_unit_id', 'finance_category_id', 'type', 'amount', 'date', 'description', 'attachment_path', 'status']),
            'workflowEnabled' => FinanceLedger::workflowEnabled(),
        ]);
    }

    public function update(Request $request, FinanceLedger $ledger)
    {
        Gate::authorize('update', $ledger);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $unitId = $isSuper ? (int) $request->input('organization_unit_id') : (int) $user->organization_unit_id;

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'finance_category_id' => ['required', 'integer', Rule::exists('finance_categories', 'id')],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'organization_unit_id' => [$isSuper ? 'required' : 'nullable'],
            'attachment' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        $category = FinanceCategory::findOrFail((int) $validated['finance_category_id']);
        if (!$isSuper) {
            if (!is_null($category->organization_unit_id) && (int) $category->organization_unit_id !== (int) $unitId) {
                return back()->withErrors(['finance_category_id' => 'Kategori tidak valid untuk unit']);
            }
        }

        $attachmentPath = $ledger->attachment_path;
        if ($request->hasFile('attachment')) {
            if ($attachmentPath)
                Storage::delete('public/' . $attachmentPath);
            $newPath = $request->file('attachment')->store('public/finance/attachments');
            $attachmentPath = str_replace('public/', '', $newPath);
        }

        $oldData = $ledger->only(['amount', 'type', 'status']);

        $ledger->update([
            'organization_unit_id' => $unitId,
            'finance_category_id' => (int) $validated['finance_category_id'],
            'type' => $validated['type'] ?? $category->type,
            'amount' => (float) $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'ledger_updated',
            'subject_type' => FinanceLedger::class,
            'subject_id' => $ledger->id,
            'payload' => [
                'old' => $oldData,
                'new' => ['amount' => $ledger->amount, 'type' => $ledger->type, 'status' => $ledger->status],
            ],
        ]);

        return redirect()->route('finance.ledgers.index')->with('success', 'Transaksi berhasil diperbarui');
    }

    public function destroy(FinanceLedger $ledger)
    {
        Gate::authorize('delete', $ledger);
        $user = Auth::user();

        $logData = ['amount' => $ledger->amount, 'type' => $ledger->type, 'status' => $ledger->status];

        if ($ledger->attachment_path)
            Storage::delete('public/' . $ledger->attachment_path);
        $ledger->delete();

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'ledger_deleted',
            'subject_type' => FinanceLedger::class,
            'subject_id' => $ledger->id,
            'payload' => $logData,
        ]);

        return redirect()->route('finance.ledgers.index')->with('success', 'Transaksi berhasil dihapus');
    }

    public function approve(FinanceLedger $ledger)
    {
        Gate::authorize('approve', $ledger);
        $user = Auth::user();
        $oldStatus = $ledger->status;

        $ledger->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'ledger_approved',
            'subject_type' => FinanceLedger::class,
            'subject_id' => $ledger->id,
            'payload' => [
                'old_status' => $oldStatus,
                'new_status' => 'approved',
                'amount' => $ledger->amount,
                'type' => $ledger->type,
            ],
        ]);

        $creator = $ledger->creator;
        if ($creator) {
            try { $creator->notify(new FinanceLedgerApprovedNotification($ledger)); } catch (\Throwable $e) {}
        }

        return back()->with('success', 'Transaksi berhasil disetujui');
    }

    public function reject(Request $request, FinanceLedger $ledger)
    {
        Gate::authorize('reject', $ledger);
        $user = Auth::user();

        $validated = $request->validate([
            'rejected_reason' => ['required', 'string', 'max:500'],
        ]);

        $oldStatus = $ledger->status;

        $ledger->update([
            'status' => 'rejected',
            'rejected_reason' => $validated['rejected_reason'],
            'approved_by' => null,
            'approved_at' => null,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'ledger_rejected',
            'subject_type' => FinanceLedger::class,
            'subject_id' => $ledger->id,
            'payload' => [
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
                'reason' => $validated['rejected_reason'],
                'amount' => $ledger->amount,
                'type' => $ledger->type,
            ],
        ]);

        $creator = $ledger->creator;
        if ($creator) {
            try { $creator->notify(new FinanceLedgerRejectedNotification($ledger, $validated['rejected_reason'])); } catch (\Throwable $e) {}
        }

        return back()->with('success', 'Transaksi ditolak');
    }

    public function export(Request $request)
    {
        Gate::authorize('viewAny', FinanceLedger::class);
        $user = Auth::user();
        $isSuper = $user && $user->role && $user->role->name === 'super_admin';
        $query = FinanceLedger::query()->with(['category', 'organizationUnit', 'creator']);

        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $categoryId = $request->query('category_id');
        $type = $request->query('type');
        $status = $request->query('status');
        $search = $request->query('search');
        $unitParam = $request->query('unit_id');

        if (!$isSuper) {
            $query->where('organization_unit_id', $user->organization_unit_id);
        } else {
            if ($unitParam)
                $query->where('organization_unit_id', (int) $unitParam);
        }
        if ($dateStart)
            $query->whereDate('date', '>=', $dateStart);
        if ($dateEnd)
            $query->whereDate('date', '<=', $dateEnd);
        if ($categoryId)
            $query->where('finance_category_id', (int) $categoryId);
        if ($type && in_array($type, ['income', 'expense']))
            $query->where('type', $type);
        if ($status && in_array($status, ['draft', 'submitted', 'approved', 'rejected']))
            $query->where('status', $status);
        if ($search)
            $query->where('description', 'like', "%{$search}%");

        $filename = 'ledgers_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Kategori', 'Tipe', 'Nominal', 'Deskripsi', 'Unit', 'Dibuat Oleh', 'Status']);
            $query->orderBy('date', 'desc')->chunk(500, function ($rows) use (&$out) {
                foreach ($rows as $l) {
                    fputcsv($out, [
                        $l->date instanceof \Carbon\Carbon ? $l->date->format('Y-m-d') : $l->date,
                        $l->category?->name ?? '-',
                        $l->type === 'income' ? 'Pemasukan' : 'Pengeluaran',
                        $l->amount,
                        $l->description ?? '-',
                        $l->organizationUnit?->name ?? '-',
                        $l->creator?->name ?? '-',
                        $l->status ?? 'submitted',
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
