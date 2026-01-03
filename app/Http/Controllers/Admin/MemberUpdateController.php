<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberUpdateRequest;
use App\Models\Member;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberUpdateController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', MemberUpdateRequest::class);

        $user = $request->user();
        $unitId = $user->currentUnitId();

        // Build base query with unit scope for non-global users
        $baseQuery = MemberUpdateRequest::query();
        if (!$user->hasGlobalAccess()) {
            $baseQuery->whereHas('member', function ($q) use ($unitId) {
                $q->where('organization_unit_id', $unitId);
            });
        }

        $query = (clone $baseQuery)->with('member');
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $items = $query->latest()->paginate(10)->withQueryString();

        // Stats from scoped base query (not global)
        $statsQuery = clone $baseQuery;

        return Inertia::render('Admin/Updates/Index', [
            'items' => $items,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
                'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(Request $request, MemberUpdateRequest $update_request)
    {
        Gate::authorize('approve', $update_request);

        $member = $update_request->member;
        if (!$member) {
            return back()->with('error', 'Member tidak ditemukan');
        }

        $member->update($update_request->new_data);
        $update_request->status = 'approved';
        $update_request->reviewer_id = $request->user()->id;
        $update_request->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'member_update_approved',
            'subject_type' => MemberUpdateRequest::class,
            'subject_id' => $update_request->id,
            'payload' => ['member_id' => $member->id],
        ]);

        $owner = $member->user_id ? \App\Models\User::find($member->user_id) : null;
        if ($owner) {
            try {
                $owner->notify(new \App\Notifications\MemberUpdateApprovedNotification($update_request));
            } catch (\Throwable $e) {
            }
        }

        return back()->with('success', 'Perubahan disetujui');
    }

    public function reject(Request $request, MemberUpdateRequest $update_request)
    {
        Gate::authorize('reject', $update_request);

        $validated = $request->validate(['notes' => 'required|string|min:5']);

        $update_request->status = 'rejected';
        $update_request->notes = $validated['notes'];
        $update_request->reviewer_id = $request->user()->id;
        $update_request->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'member_update_rejected',
            'subject_type' => MemberUpdateRequest::class,
            'subject_id' => $update_request->id,
            'payload' => ['member_id' => $update_request->member_id],
        ]);

        $owner = $update_request->member?->user_id ? \App\Models\User::find($update_request->member->user_id) : null;
        if ($owner) {
            try {
                $owner->notify(new \App\Notifications\MemberUpdateRejectedNotification($update_request));
            } catch (\Throwable $e) {
            }
        }

        return back()->with('success', 'Perubahan ditolak');
    }
}
