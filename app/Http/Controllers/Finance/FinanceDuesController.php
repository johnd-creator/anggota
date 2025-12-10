<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\DuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinanceDuesController extends Controller
{
    protected DuesService $duesService;

    public function __construct(DuesService $duesService)
    {
        $this->duesService = $duesService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $period = $request->input('period', now()->format('Y-m'));
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $unitId = $request->input('unit_id');

        // Determine unit scope
        if ($user->hasGlobalAccess()) {
            // Super admin and admin_pusat can view all or filter by unit
            $unitScope = $unitId ? [(int) $unitId] : null;
        } else {
            // Bendahara/admin_unit can only see their unit
            $unitScope = [$user->organization_unit_id];
        }

        // Build query: members with left join to dues_payments
        $query = Member::query()
            ->select([
                'members.id',
                'members.full_name',
                'members.kta_number',
                'members.organization_unit_id',
                'dues_payments.status as dues_status',
                'dues_payments.amount',
                'dues_payments.paid_at',
                'dues_payments.notes',
                'dues_payments.id as dues_payment_id',
            ])
            ->leftJoin('dues_payments', function ($join) use ($period) {
                $join->on('members.id', '=', 'dues_payments.member_id')
                    ->where('dues_payments.period', '=', $period);
            })
            ->where('members.status', 'aktif');

        // Apply unit scope
        if ($unitScope) {
            $query->whereIn('members.organization_unit_id', $unitScope);
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('members.full_name', 'like', "%{$search}%")
                    ->orWhere('members.kta_number', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status === 'paid') {
            $query->where('dues_payments.status', 'paid');
        } elseif ($status === 'unpaid') {
            $query->where(function ($q) {
                $q->whereNull('dues_payments.status')
                    ->orWhere('dues_payments.status', 'unpaid');
            });
        }

        $query->orderBy('members.full_name');

        $members = $query->paginate(20)->withQueryString();

        // Transform data for frontend
        $members->getCollection()->transform(function ($member) {
            return [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'kta_number' => $member->kta_number,
                'organization_unit_id' => $member->organization_unit_id,
                'dues_status' => $member->dues_status ?? 'unpaid',
                'amount' => $member->amount,
                'paid_at' => $member->paid_at,
                'notes' => $member->notes,
                'dues_payment_id' => $member->dues_payment_id,
            ];
        });

        // Get summary stats
        $summaryQuery = Member::query()
            ->where('members.status', 'aktif');

        if ($unitScope) {
            $summaryQuery->whereIn('members.organization_unit_id', $unitScope);
        }

        $totalMembers = $summaryQuery->count();

        $paidCount = DuesPayment::query()
            ->where('period', $period)
            ->where('status', 'paid');

        if ($unitScope) {
            $paidCount->whereIn('organization_unit_id', $unitScope);
        }

        $paidCount = $paidCount->count();

        // Get units for super_admin filter
        $units = [];
        if ($user->hasRole('super_admin')) {
            $units = OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get();
        }

        // Get recurring categories for quick action
        $userUnitId = $user->hasRole('super_admin') ? null : $user->organization_unit_id;
        $recurringCategories = FinanceCategory::query()
            ->recurring()
            ->where('type', 'income')
            ->forUnit($userUnitId)
            ->select('id', 'name', 'default_amount')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance/Dues/Index', [
            'members' => $members,
            'filters' => [
                'period' => $period,
                'search' => $search,
                'status' => $status,
                'unit_id' => $unitId,
            ],
            'summary' => [
                'total' => $totalMembers,
                'paid' => $paidCount,
                'unpaid' => $totalMembers - $paidCount,
            ],
            'units' => $units,
            'canSelectUnit' => $user->hasRole('super_admin'),
            'recurringCategories' => $recurringCategories,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['required', 'in:paid,unpaid'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validate amount when marking as paid
        if ($validated['status'] === 'paid') {
            if (empty($validated['amount']) || $validated['amount'] <= 0) {
                return back()->withErrors(['amount' => 'Nominal harus diisi dan lebih dari 0 untuk status Sudah Bayar']);
            }
        }

        $success = $this->duesService->recordSinglePayment(
            $validated['member_id'],
            $validated['period'],
            $validated['status'],
            $validated['amount'] ?? null,
            $validated['notes'] ?? null,
            $user
        );

        if (!$success) {
            abort(403, 'Anda tidak memiliki akses ke anggota unit ini');
        }

        return back()->with('success', $validated['status'] === 'paid'
            ? 'Status iuran berhasil diperbarui menjadi Sudah Bayar'
            : 'Status iuran berhasil diperbarui menjadi Belum Bayar');
    }

    /**
     * Mass update dues for multiple members
     */
    public function massUpdate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'category_id' => ['required', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->duesService->recordPaymentBatch(
            $validated['member_ids'],
            $validated['period'],
            $validated['category_id'],
            $validated['amount'],
            $validated['notes'] ?? null,
            $user
        );

        $message = "{$result['success']} anggota berhasil ditandai sudah bayar";
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} anggota sudah paid sebelumnya (dilewati)";
        }

        return back()->with('success', $message);
    }

    /**
     * Get summary for dashboard
     */
    public static function getDashboardSummary(int $unitId = null, string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');

        $membersQuery = Member::where('status', 'aktif');
        $paidQuery = DuesPayment::where('period', $period)->where('status', 'paid');

        if ($unitId) {
            $membersQuery->where('organization_unit_id', $unitId);
            $paidQuery->where('organization_unit_id', $unitId);
        }

        $total = $membersQuery->count();
        $paid = $paidQuery->count();

        return [
            'total' => $total,
            'paid' => $paid,
            'unpaid' => $total - $paid,
            'period' => $period,
        ];
    }

    /**
     * Get list of unpaid members for dashboard modal
     */
    public static function getUnpaidMembers(int $unitId = null, string $period = null, int $limit = 50): array
    {
        $period = $period ?? now()->format('Y-m');

        $query = Member::query()
            ->select(['members.id', 'members.full_name', 'members.kta_number'])
            ->leftJoin('dues_payments', function ($join) use ($period) {
                $join->on('members.id', '=', 'dues_payments.member_id')
                    ->where('dues_payments.period', '=', $period);
            })
            ->where('members.status', 'aktif')
            ->where(function ($q) {
                $q->whereNull('dues_payments.status')
                    ->orWhere('dues_payments.status', 'unpaid');
            });

        if ($unitId) {
            $query->where('members.organization_unit_id', $unitId);
        }

        return $query->orderBy('members.full_name')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
