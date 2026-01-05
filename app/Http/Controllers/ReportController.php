<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use App\Models\OrganizationUnit;

class ReportController extends Controller
{
    public function growth(Request $request)
    {
        Gate::authorize('export', \App\Models\Member::class);
        $user = $request->user();
        $requestedUnitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $unitId = \App\Services\ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);

        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');

        $query = \App\Models\Member::query();
        if ($unitId)
            $query->where('organization_unit_id', $unitId);
        if ($dateStart)
            $query->whereDate('join_date', '>=', $dateStart);
        if ($dateEnd)
            $query->whereDate('join_date', '<=', $dateEnd);

        $months = collect(range(0, 11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
        $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))
            ->groupBy('ym')->get()->keyBy('ym');
        $series = $months->map(fn($m) => ['label' => $m, 'value' => (int) optional($rows->get($m))->c]);
        $total = (int) $query->count();

        return Inertia::render('Reports/Growth', [
            'series' => $series,
            'kpi' => ['total' => $total],
            'filters' => array_merge($request->only(['date_start', 'date_end']), ['unit_id' => $unitId]),
            'last_updated' => now()->toDateString(),
            'units' => OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function mutations(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\MutationRequest::class);
        $user = $request->user();
        $requestedUnitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $unitId = \App\Services\ExportScopeHelper::getEffectiveUnitId($user, $requestedUnitId);

        $status = $request->query('status');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');

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

        $total = (clone $query)->count();
        $unitNames = OrganizationUnit::select('id', 'name')->get()->pluck('name', 'id');
        $dist = (clone $query)->select('to_unit_id', DB::raw('count(*) as c'))
            ->groupBy('to_unit_id')->orderByDesc('c')->limit(10)->get()
            ->map(function ($row) use ($unitNames) {
                return [
                    'to_unit_id' => $row->to_unit_id,
                    'to_unit_name' => $unitNames[$row->to_unit_id] ?? '-',
                    'count' => (int) $row->c,
                ];
            });

        return Inertia::render('Reports/Mutations', [
            'dist' => $dist,
            'kpi' => ['total' => $total],
            'filters' => array_merge($request->only(['status', 'date_start', 'date_end']), ['unit_id' => $unitId]),
            'last_updated' => now()->toDateString(),
            'units' => OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
        ]);
    }



    public function apiGrowth(Request $request)
    {
        $unitId = $request->query('unit_id');
        if (!$unitId)
            return response()->json(['error' => 'unit_id required'], 400);
        $unitId = (int) $unitId;

        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $query = \App\Models\Member::query();
        $query->where('organization_unit_id', $unitId);

        if ($dateStart)
            $query->whereDate('join_date', '>=', $dateStart);
        if ($dateEnd)
            $query->whereDate('join_date', '<=', $dateEnd);
        $months = collect(range(0, 11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
        $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))->groupBy('ym')->get()->keyBy('ym');
        $series = $months->map(fn($m) => ['label' => $m, 'value' => (int) optional($rows->get($m))->c]);
        return response()->json(['series' => $series]);
    }

    public function apiMutations(Request $request)
    {
        $unitId = $request->query('unit_id');
        if (!$unitId)
            return response()->json(['error' => 'unit_id required'], 400);
        $unitId = (int) $unitId;

        $status = $request->query('status');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $query = \App\Models\MutationRequest::query();
        $query->where(function ($q) use ($unitId) {
            $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
        });

        if ($status)
            $query->where('status', $status);
        if ($dateStart)
            $query->whereDate('effective_date', '>=', $dateStart);
        if ($dateEnd)
            $query->whereDate('effective_date', '<=', $dateEnd);
        $dist = $query->select('to_unit_id', DB::raw('count(*) as c'))->groupBy('to_unit_id')->orderByDesc('c')->limit(10)->get();
        return response()->json(['dist' => $dist]);
    }



    public function members(Request $request)
    {
        Gate::authorize('export', \App\Models\Member::class);
        return Inertia::render('Reports/Members', [
            'units' => \App\Models\OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
            'union_positions' => \App\Models\UnionPosition::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function aspirations(Request $request)
    {
        Gate::authorize('export', \App\Models\Aspiration::class);
        return Inertia::render('Reports/Aspirations', [
            'units' => \App\Models\OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function dues(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\DuesPayment::class);
        return Inertia::render('Reports/Dues', [
            'units' => \App\Models\OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function finance(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\FinanceLedger::class);
        return Inertia::render('Reports/Finance', [
            'units' => \App\Models\OrganizationUnit::select('id', 'name')->orderBy('name')->get(),
            'finance_categories' => \App\Models\FinanceCategory::select('id', 'name', 'type')->orderBy('name')->get(),
        ]);
    }

}
