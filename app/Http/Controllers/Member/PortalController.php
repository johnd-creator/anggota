<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $member = Member::with(['unit','documents','statusLogs'])->where('user_id', $user->id)->first();
        return Inertia::render('Member/Portal', [
            'member' => $member,
            'updateRequests' => $member ? MemberUpdateRequest::where('member_id', $member->id)->latest()->limit(10)->get() : [],
            'notifications' => $user ? \App\Models\Notification::where('notifiable_type', \App\Models\User::class)->where('notifiable_id', $user->id)->latest()->limit(10)->get() : [],
        ]);
    }

    public function requestUpdate(Request $request)
    {
        $user = Auth::user();
        $member = Member::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'address' => 'nullable|string',
            'phone' => ['nullable','regex:/^\+?[1-9]\d{7,14}$/'],
        ]);

        $req = MemberUpdateRequest::create([
            'member_id' => $member->id,
            'old_data' => [ 'address' => $member->address, 'phone' => $member->phone ],
            'new_data' => $validated,
            'status' => 'pending',
            'notes' => $request->input('notes'),
        ]);

        return redirect()->back()->with('success', 'Permintaan perubahan dikirim');
    }
}
