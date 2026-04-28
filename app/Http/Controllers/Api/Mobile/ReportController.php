<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\MobileApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Aspiration;
use App\Models\DuesPayment;
use App\Models\FinanceLedger;
use App\Models\Member;
use App\Models\MutationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    use MobileApiHelpers;

    public function growth(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Member::class);
        $query = Member::query();
        $this->scopeUnitQuery($query, $request->user());

        return response()->json([
            'items' => $query
                ->selectRaw("strftime('%Y-%m', join_date) as period, count(*) as total")
                ->whereNotNull('join_date')
                ->groupBy('period')
                ->orderBy('period')
                ->get(),
        ]);
    }

    public function mutations(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MutationRequest::class);
        $query = MutationRequest::query();
        if (! $request->user()->canViewGlobalScope()) {
            $unitId = $request->user()->currentUnitId();
            $query->where(fn ($q) => $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId));
        }

        return response()->json(['summary' => $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function members(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Member::class);
        $query = Member::query();
        $this->scopeUnitQuery($query, $request->user());

        return response()->json([
            'summary' => [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
                'inactive' => (clone $query)->where('status', 'inactive')->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
            ],
        ]);
    }

    public function aspirations(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyAdmin', Aspiration::class);
        $query = Aspiration::query();
        $this->scopeUnitQuery($query, $request->user());

        return response()->json(['summary' => $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function dues(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(['super_admin', 'admin_pusat', 'bendahara', 'bendahara_pusat']), 403);
        Gate::authorize('viewAny', DuesPayment::class);
        $query = DuesPayment::query();
        if (! $request->user()->canViewGlobalScope()) {
            $query->where('organization_unit_id', $request->user()->currentUnitId());
        }

        return response()->json([
            'summary' => [
                'total' => (clone $query)->count(),
                'paid' => (clone $query)->where('status', 'paid')->count(),
                'unpaid' => (clone $query)->where('status', 'unpaid')->count(),
                'amount' => (float) (clone $query)->sum('amount'),
            ],
        ]);
    }

    public function finance(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FinanceLedger::class);
        $query = FinanceLedger::query();
        $this->scopeUnitQuery($query, $request->user());

        return response()->json([
            'summary' => [
                'income' => (float) (clone $query)->where('type', 'income')->sum('amount'),
                'expense' => (float) (clone $query)->where('type', 'expense')->sum('amount'),
                'pending' => (clone $query)->where('status', 'submitted')->count(),
            ],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'queued',
            'export_id' => 'mobile-'.now()->format('YmdHis').'-'.$request->user()->id,
            'filters' => $request->query(),
        ], 202);
    }

    public function exportStatus(string $id): JsonResponse
    {
        return response()->json([
            'export_id' => $id,
            'status' => 'queued',
            'download_url' => null,
        ]);
    }
}
