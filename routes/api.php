<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api_token','throttle:60,1'])->group(function(){
    Route::get('/members', function(\Illuminate\Http\Request $request){
        $unitId = (int) $request->query('unit_id');
        $query = \App\Models\Member::query()->select('id','full_name','email','phone','status','organization_unit_id');
        if ($unitId) $query->where('organization_unit_id', $unitId);
        return response()->json($query->limit(100)->get());
    });

    Route::get('/mutations', function(\Illuminate\Http\Request $request){
        $items = \App\Models\MutationRequest::with(['member:id,full_name','fromUnit:id,name','toUnit:id,name'])
            ->latest()->limit(100)->get();
        return response()->json($items);
    });

    Route::get('/documents', function(\Illuminate\Http\Request $request){
        $memberId = (int) $request->query('member_id');
        $q = \App\Models\MemberDocument::query()->select('member_id','type','path','original_name','size');
        if ($memberId) $q->where('member_id', $memberId);
        return response()->json($q->limit(100)->get());
    });
});

