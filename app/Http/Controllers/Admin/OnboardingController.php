<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PendingMember;
use App\Models\Member;
use App\Models\ActivityLog;
use App\Services\NraGenerator;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PendingMember::class); // allow super_admin, admin_unit via middleware

        $query = PendingMember::query()->with('unit')->where('status', 'pending');
        $user = $request->user();
        // admin_unit sees only their unit; admin_pusat and super_admin see all
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id) {
                $query->where('organization_unit_id', $user->organization_unit_id);
            } else {
                $query->whereRaw('1=0');
            }
        }
        // admin_pusat and super_admin have global access - no unit filter

        $pendings = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Admin/Onboarding/Index', [
            'items' => $pendings,
            'units' => \App\Models\OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get(),
            'positions' => \App\Models\UnionPosition::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => PendingMember::count(),
                'pending' => PendingMember::where('status', 'pending')->count(),
                'approved' => PendingMember::where('status', 'approved')->count(),
                'rejected' => PendingMember::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(Request $request, PendingMember $pending)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'organization_unit_id' => 'required|exists:organization_units,id',
            'join_date' => 'required|date',
            'nip' => 'required|alpha_num|max:50',
            'union_position_id' => 'required|exists:union_positions,id',
        ]);

        $user = $request->user();
        // admin_unit approves to their own unit; admin_pusat/super_admin can approve to any unit
        if ($user && $user->role && $user->role->name === 'admin_unit' && $user->organization_unit_id) {
            $validated['organization_unit_id'] = $user->organization_unit_id;
        }

        $joinYear = (int) date('Y', strtotime($validated['join_date']));
        $unitId = (int) $validated['organization_unit_id'];
        $gen = NraGenerator::generate($unitId, $joinYear);

        $kta = \App\Services\KtaGenerator::generate($unitId, $joinYear);
        $member = Member::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'employment_type' => 'organik',
            'status' => 'aktif',
            'join_date' => $validated['join_date'],
            'organization_unit_id' => $unitId,
            'nra' => $gen['nra'],
            'join_year' => $joinYear,
            'sequence_number' => $gen['sequence'],
            'user_id' => $pending->user_id,
            'kta_number' => $kta['kta'],
            'nip' => $validated['nip'],
            'union_position_id' => $validated['union_position_id'],
        ]);

        if ($pending->user_id) {
            $user = \App\Models\User::find($pending->user_id);
            if ($user) {
                $user->assignMember($member);
            }
        }

        $pending->status = 'approved';
        $pending->reviewer_id = $request->user()->id;
        $pending->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'onboarding_approved',
            'subject_type' => PendingMember::class,
            'subject_id' => $pending->id,
            'payload' => ['member_id' => $member->id, 'nra' => $member->nra],
        ]);

        if ($pending->user_id) {
            $target = \App\Models\User::find($pending->user_id);
            if ($target) {
                $target->notify(new \App\Notifications\OnboardingApprovedNotification($member));
            }
        }

        return redirect()->back()->with('success', 'Onboarding disetujui');
    }

    public function reject(Request $request, PendingMember $pending)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $pending->status = 'rejected';
        $pending->notes = $validated['reason'];
        $pending->reviewer_id = $request->user()->id;
        $pending->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'onboarding_rejected',
            'subject_type' => PendingMember::class,
            'subject_id' => $pending->id,
            'payload' => ['reason' => $validated['reason']],
        ]);

        if ($pending->user_id) {
            $target = \App\Models\User::find($pending->user_id);
            if ($target) {
                $target->notify(new \App\Notifications\OnboardingRejectedNotification($pending));
            }
        }

        return redirect()->back()->with('success', 'Onboarding ditolak');
    }
}
