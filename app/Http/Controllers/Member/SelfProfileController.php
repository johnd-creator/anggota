<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SelfProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $member = Member::with(['unit','documents','statusLogs'])->where('user_id', $user->id)->first();
        return Inertia::render('Member/Profile', [
            'member' => $member,
            'updateRequests' => $member ? MemberUpdateRequest::where('member_id', $member->id)->latest()->limit(10)->get() : [],
        ]);
    }
}
