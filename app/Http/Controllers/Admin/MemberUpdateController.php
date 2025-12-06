<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberUpdateRequest;
use App\Models\Member;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberUpdateController extends Controller
{
    public function index(Request $request)
    {
        $query = MemberUpdateRequest::query()->with('member');
        if ($status = $request->get('status')) $query->where('status', $status);
        $items = $query->latest()->paginate(10)->withQueryString();
        return Inertia::render('Admin/Updates/Index', [ 'items' => $items ]);
    }

    public function approve(Request $request, MemberUpdateRequest $requestModel)
    {
        $member = $requestModel->member;
        $member->update($requestModel->new_data);
        $requestModel->status = 'approved';
        $requestModel->reviewer_id = $request->user()->id;
        $requestModel->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'member_update_approved',
            'subject_type' => MemberUpdateRequest::class,
            'subject_id' => $requestModel->id,
            'payload' => ['member_id' => $member->id],
        ]);

        $owner = $member->user_id ? \App\Models\User::find($member->user_id) : null;
        if ($owner) {
            $owner->notify(new \App\Notifications\MemberUpdateApprovedNotification($requestModel));
        }

        return back()->with('success', 'Perubahan disetujui');
    }

    public function reject(Request $request, MemberUpdateRequest $requestModel)
    {
        $requestModel->status = 'rejected';
        $requestModel->notes = $request->validate(['notes' => 'required|string|min:5'])['notes'];
        $requestModel->reviewer_id = $request->user()->id;
        $requestModel->save();
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'member_update_rejected',
            'subject_type' => MemberUpdateRequest::class,
            'subject_id' => $requestModel->id,
            'payload' => ['member_id' => $requestModel->member_id],
        ]);
        $owner = $requestModel->member_id ? \App\Models\User::where('member_id', $requestModel->member_id)->first() : null;
        if ($owner) {
            $owner->notify(new \App\Notifications\MemberUpdateRejectedNotification($requestModel));
        }
        return back()->with('success', 'Perubahan ditolak');
    }
}
