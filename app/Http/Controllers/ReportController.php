<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function growth(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');

        $query = \App\Models\Member::query();
        if ($unitId) $query->where('organization_unit_id', $unitId);
        if ($dateStart) $query->whereDate('join_date', '>=', $dateStart);
        if ($dateEnd) $query->whereDate('join_date', '<=', $dateEnd);

        $months = collect(range(0,11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
        $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))
            ->groupBy('ym')->get()->keyBy('ym');
        $series = $months->map(fn($m) => ['label' => $m, 'value' => (int) optional($rows->get($m))->c]);
        $total = (int) $query->count();

        return Inertia::render('Reports/Growth', [
            'series' => $series,
            'kpi' => ['total' => $total],
            'filters' => $request->only(['unit_id','date_start','date_end']),
            'last_updated' => now()->toDateString(),
        ]);
    }

    public function mutations(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $status = $request->query('status');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');

        $query = \App\Models\MutationRequest::query()->with(['member','fromUnit','toUnit']);
        if ($unitId) $query->where(function($q) use ($unitId){ $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId); });
        if ($status) $query->where('status', $status);
        if ($dateStart) $query->whereDate('effective_date', '>=', $dateStart);
        if ($dateEnd) $query->whereDate('effective_date', '<=', $dateEnd);

        $dist = $query->select('to_unit_id', DB::raw('count(*) as c'))
            ->groupBy('to_unit_id')->orderByDesc('c')->limit(10)->get();

        return Inertia::render('Reports/Mutations', [
            'dist' => $dist,
            'filters' => $request->only(['unit_id','status','date_start','date_end']),
            'last_updated' => now()->toDateString(),
        ]);
    }

    public function documents(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $status = $request->query('status');

        $members = \App\Models\Member::query()->select('id','full_name','email','organization_unit_id','photo_path','documents','kta_number','nip','union_position_id')
            ->with(['unit','unionPosition']);
        if ($unitId) $members->where('organization_unit_id', $unitId);
        if ($status) $members->where('status', $status);

        $rows = $members->orderBy('id')->paginate(20)->withQueryString();
        $complete = (int) \App\Models\Member::whereNotNull('photo_path')->count();
        $missing = (int) \App\Models\Member::whereNull('photo_path')->orWhereNull('documents')->count();

        return Inertia::render('Reports/Documents', [
            'items' => $rows,
            'kpi' => ['complete' => $complete, 'missing' => $missing],
            'filters' => $request->only(['unit_id','status']),
            'last_updated' => now()->toDateString(),
        ]);
    }

    public function apiGrowth(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $query = \App\Models\Member::query();
        if ($unitId) $query->where('organization_unit_id', $unitId);
        if ($dateStart) $query->whereDate('join_date', '>=', $dateStart);
        if ($dateEnd) $query->whereDate('join_date', '<=', $dateEnd);
        $months = collect(range(0,11))->map(fn($i) => now()->subMonths(11 - $i)->format('Y-m'));
        $rows = $query->select(DB::raw("strftime('%Y-%m', join_date) as ym"), DB::raw('count(*) as c'))->groupBy('ym')->get()->keyBy('ym');
        $series = $months->map(fn($m) => ['label' => $m, 'value' => (int) optional($rows->get($m))->c]);
        return response()->json(['series' => $series]);
    }

    public function apiMutations(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $status = $request->query('status');
        $dateStart = $request->query('date_start');
        $dateEnd = $request->query('date_end');
        $query = \App\Models\MutationRequest::query();
        if ($unitId) $query->where(function($q) use ($unitId){ $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId); });
        if ($status) $query->where('status', $status);
        if ($dateStart) $query->whereDate('effective_date', '>=', $dateStart);
        if ($dateEnd) $query->whereDate('effective_date', '<=', $dateEnd);
        $dist = $query->select('to_unit_id', DB::raw('count(*) as c'))->groupBy('to_unit_id')->orderByDesc('c')->limit(10)->get();
        return response()->json(['dist' => $dist]);
    }

    public function apiDocuments(Request $request)
    {
        $unitId = (int) $request->query('unit_id');
        $status = $request->query('status');
        $members = \App\Models\Member::query()->select('id','full_name','email','organization_unit_id','photo_path','documents','kta_number','nip','union_position_id')->with('unionPosition');
        if ($unitId) $members->where('organization_unit_id', $unitId);
        if ($status) $members->where('status', $status);
        $rows = $members->orderBy('id')->limit(100)->get();
        return response()->json(['items' => $rows]);
    }
}
