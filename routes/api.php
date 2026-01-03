<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api_token', 'throttle:60,1'])->group(function () {
    Route::get('/members', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');

        // Require unit_id parameter
        if (!$unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;
        $query = \App\Models\Member::query()
            ->select('id', 'full_name', 'status', 'organization_unit_id', 'nra', 'kta_number')
            ->where('organization_unit_id', $unitId);

        return response()->json($query->limit(100)->get());
    });

    Route::get('/mutations', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');

        // Require unit_id parameter
        if (!$unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;
        $items = \App\Models\MutationRequest::with(['member:id,full_name', 'fromUnit:id,name', 'toUnit:id,name'])
            ->where(function ($q) use ($unitId) {
                $q->where('from_unit_id', $unitId)->orWhere('to_unit_id', $unitId);
            })
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($items);
    });

    Route::get('/documents', function (\Illuminate\Http\Request $request) {
        $unitId = $request->query('unit_id');
        $memberId = (int) $request->query('member_id');

        // Require unit_id parameter
        if (!$unitId) {
            return response()->json(['error' => 'unit_id parameter is required'], 400);
        }

        $unitId = (int) $unitId;

        // Get member IDs in this unit
        $memberIds = \App\Models\Member::where('organization_unit_id', $unitId)
            ->pluck('id');

        $q = \App\Models\MemberDocument::query()
            ->select('member_id', 'type', 'original_name', 'size')
            ->whereIn('member_id', $memberIds);

        if ($memberId) {
            // Verify member is in the requested unit
            if (!$memberIds->contains($memberId)) {
                return response()->json(['error' => 'Member not in specified unit'], 403);
            }
            $q->where('member_id', $memberId);
        }

        return response()->json($q->limit(100)->get());
    });
});
