<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\DuesPaymentResource;
use App\Http\Resources\Mobile\FinanceResource;
use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    use MobileApiHelpers;

    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FinanceCategory::class);
        $query = FinanceCategory::query()->with('organizationUnit:id,name,code')->orderBy('name');
        if (! $request->user()->canViewGlobalScope()) {
            $unitId = $request->user()->currentUnitId();
            $query->where(fn ($q) => $q->whereNull('organization_unit_id')->orWhere('organization_unit_id', $unitId));
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return $this->paginated($query->paginate($this->perPage($request)), 'categories');
    }

    public function categoryStore(Request $request): JsonResponse
    {
        Gate::authorize('create', FinanceCategory::class);
        $category = FinanceCategory::create($this->validatedCategory($request) + ['created_by' => $request->user()->id]);

        return response()->json(['status' => 'ok', 'category' => $category], 201);
    }

    public function categoryUpdate(Request $request, FinanceCategory $category): JsonResponse
    {
        Gate::authorize('update', $category);
        $category->update($this->validatedCategory($request, $category));

        return $this->ok(['category' => $category->fresh()]);
    }

    public function categoryDestroy(FinanceCategory $category): JsonResponse
    {
        Gate::authorize('delete', $category);
        abort_if($category->ledgers()->exists(), 422, 'Kategori sudah dipakai ledger.');
        $category->delete();

        return $this->ok();
    }

    public function ledgers(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FinanceLedger::class);
        $query = FinanceLedger::with(['category', 'organizationUnit:id,name,code', 'creator:id,name'])->latest('date');
        $this->scopeUnitQuery($query, $request->user());
        foreach (['type', 'status', 'finance_category_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $paginator = $query->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (FinanceLedger $ledger) => new FinanceResource($ledger));

        return $this->paginated($paginator, 'ledgers');
    }

    public function ledgerStore(Request $request): JsonResponse
    {
        Gate::authorize('create', FinanceLedger::class);
        $data = $this->validatedLedger($request);
        $unitId = $this->resolvedUnitId($request, $data['organization_unit_id'] ?? null);

        $ledger = FinanceLedger::create($data + [
            'organization_unit_id' => $unitId,
            'status' => FinanceLedger::defaultStatus(),
            'submitted_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['status' => 'ok', 'ledger' => new FinanceResource($ledger->load(['category', 'organizationUnit', 'creator']))], 201);
    }

    public function ledgerUpdate(Request $request, FinanceLedger $ledger): JsonResponse
    {
        Gate::authorize('update', $ledger);
        $data = $this->validatedLedger($request, $ledger);
        $data['organization_unit_id'] = $this->resolvedUnitId($request, $data['organization_unit_id'] ?? $ledger->organization_unit_id);
        $ledger->update($data);

        return $this->ok(['ledger' => new FinanceResource($ledger->fresh()->load(['category', 'organizationUnit', 'creator']))]);
    }

    public function ledgerDestroy(FinanceLedger $ledger): JsonResponse
    {
        Gate::authorize('delete', $ledger);
        $ledger->delete();

        return $this->ok();
    }

    public function ledgerApprove(Request $request, FinanceLedger $ledger): JsonResponse
    {
        Gate::authorize('approve', $ledger);
        $ledger->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'rejected_reason' => null]);

        return $this->ok(['ledger' => new FinanceResource($ledger->fresh()->load(['category', 'organizationUnit', 'creator']))]);
    }

    public function ledgerReject(Request $request, FinanceLedger $ledger): JsonResponse
    {
        Gate::authorize('reject', $ledger);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $ledger->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => null, 'rejected_reason' => $validated['reason']]);

        return $this->ok(['ledger' => new FinanceResource($ledger->fresh()->load(['category', 'organizationUnit', 'creator']))]);
    }

    public function ledgerExport(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FinanceLedger::class);

        return response()->json(['status' => 'queued', 'filters' => $request->only(['type', 'status', 'finance_category_id', 'from', 'to'])], 202);
    }

    public function dues(Request $request): JsonResponse
    {
        $this->authorizeDuesAdmin($request);
        Gate::authorize('viewAny', DuesPayment::class);
        $query = DuesPayment::with(['member:id,full_name,kta_number,organization_unit_id', 'organizationUnit:id,name,code'])->latest('period');
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }
        foreach (['period', 'status', 'member_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $paginator = $query->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (DuesPayment $dues) => new DuesPaymentResource($dues));

        return $this->paginated($paginator, 'dues');
    }

    public function duesUpdate(Request $request, DuesPayment $dues): JsonResponse
    {
        Gate::authorize('update', $dues);
        $dues->update($this->validatedDues($request) + ['recorded_by' => $request->user()->id]);

        return $this->ok(['dues' => new DuesPaymentResource($dues->fresh()->load(['member', 'organizationUnit']))]);
    }

    public function duesMassUpdate(Request $request): JsonResponse
    {
        $this->authorizeDuesAdmin($request);
        $data = $request->validate([
            'items' => ['required', 'array', 'max:200'],
            'items.*.member_id' => ['required', 'exists:members,id'],
            'items.*.period' => ['required', 'string', 'max:20'],
            'items.*.status' => ['required', Rule::in(['paid', 'unpaid', 'waived'])],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.paid_at' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = DB::transaction(function () use ($data, $request) {
            $count = 0;
            foreach ($data['items'] as $item) {
                $member = Member::findOrFail($item['member_id']);
                Gate::authorize('updateForMember', [DuesPayment::class, $member]);
                DuesPayment::updateOrCreate(
                    ['member_id' => $member->id, 'period' => $item['period']],
                    $item + ['organization_unit_id' => $member->organization_unit_id, 'recorded_by' => $request->user()->id]
                );
                $count++;
            }

            return $count;
        });

        return $this->ok(['updated' => $updated]);
    }

    public function duesSummary(Request $request): JsonResponse
    {
        $this->authorizeDuesAdmin($request);
        Gate::authorize('viewAny', DuesPayment::class);
        $query = DuesPayment::query();
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }
        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        return response()->json([
            'summary' => [
                'paid' => (clone $query)->where('status', 'paid')->count(),
                'unpaid' => (clone $query)->where('status', 'unpaid')->count(),
                'waived' => (clone $query)->where('status', 'waived')->count(),
                'total_amount' => (float) (clone $query)->sum('amount'),
            ],
        ]);
    }

    private function validatedCategory(Request $request, ?FinanceCategory $category = null): array
    {
        return $request->validate([
            'organization_unit_id' => ['nullable', 'exists:organization_units,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['boolean'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function validatedLedger(Request $request, ?FinanceLedger $ledger = null): array
    {
        return $request->validate([
            'organization_unit_id' => ['nullable', 'exists:organization_units,id'],
            'finance_category_id' => ['required', 'exists:finance_categories,id'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validatedDues(Request $request): array
    {
        return $request->validate([
            'status' => ['required', Rule::in(['paid', 'unpaid', 'waived'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function resolvedUnitId(Request $request, ?int $requestedUnitId): int
    {
        if ($request->user()->canViewGlobalScope()) {
            $unitId = $requestedUnitId ?: (int) $request->user()->currentUnitId();
            abort_if($unitId <= 0, 422, 'organization_unit_id wajib untuk transaksi global.');

            return $unitId;
        }

        return (int) $request->user()->currentUnitId();
    }

    private function authorizeDuesAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'bendahara', 'bendahara_pusat']), 403);
    }
}
