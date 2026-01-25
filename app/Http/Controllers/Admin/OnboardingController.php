<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PendingMember;
use App\Models\Member;
use App\Models\ActivityLog;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PendingMember::class);

        $user = $request->user();
        $unitId = $user->currentUnitId();

        // Build base query with unit scope for non-global users
        $baseQuery = PendingMember::query();
        if (!$user->hasGlobalAccess()) {
            // admin_unit can see ALL pending members (no organization_unit_id filter)
            if ($unitId) {
                // Filter only if user has a unit, but allow all pending members
                // This allows admin_unit to see pending members from SSO (NULL organization_unit_id)
                // as well as any pending members assigned to any unit
                // NOT filtering by organization_unit_id anymore
            } else {
                $baseQuery->whereRaw('1=0');
            }
        }

        // Query for pending items (respect filter.status parameter)
        $query = (clone $baseQuery)->with('unit');

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        } else {
            // Default to show pending only
            $query->where('status', 'pending');
        }

        $pendings = $query->latest()->paginate(10)->withQueryString();

        // Stats from scoped base query (not global)
        $statsQuery = clone $baseQuery;

        return Inertia::render('Admin/Onboarding/Index', [
            'items' => $pendings,
            'units' => \App\Models\OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get(),
            'positions' => \App\Models\UnionPosition::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
                'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(Request $request, PendingMember $pending)
    {
        Gate::authorize('approve', $pending);

        $user = $request->user();

        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('members', 'email')->whereNull('deleted_at'),
            ],
            'join_date' => 'required|date',
            'nip' => 'required|alpha_num|max:50',
            'union_position_id' => 'required|exists:union_positions,id',
        ];

        // Only require organization_unit_id for global users (super_admin, admin_pusat)
        // admin_unit will use their own unit automatically
        if ($user->hasGlobalAccess()) {
            $rules['organization_unit_id'] = 'required|exists:organization_units,id';
        }

        $validated = $request->validate($rules);

        // Resolve unit id:
        // - admin_unit approves into their own unit
        // - global users approve into selected unit
        $unitId = null;
        if ($user->hasGlobalAccess()) {
            $unitId = (int) $validated['organization_unit_id'];
        } else {
            $unitId = $user->currentUnitId();
            if (!$unitId) {
                throw ValidationException::withMessages([
                    'organization_unit_id' => 'Akun admin unit belum memiliki unit organisasi.',
                ]);
            }
        }

        $joinYear = (int) date('Y', strtotime($validated['join_date']));

        $member = DB::transaction(function () use ($pending, $validated, $unitId, $joinYear) {
            $existing = Member::withTrashed()->where('email', $validated['email'])->first();

            $gen = NraGenerator::generate($unitId, $joinYear);
            $kta = \App\Services\KtaGenerator::generate($unitId, $joinYear);

            if ($existing) {
                if (!$existing->trashed()) {
                    throw ValidationException::withMessages([
                        'email' => 'Email sudah terdaftar sebagai anggota.',
                    ]);
                }

                $existing->restore();
                $existing->fill([
                    'full_name' => $validated['full_name'],
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
                $existing->save();
                return $existing;
            }

            return Member::create([
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
        });

        if ($pending->user_id) {
            $targetUser = \App\Models\User::find($pending->user_id);
            if ($targetUser) {
                $targetUser->assignMember($member);
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
        Gate::authorize('reject', $pending);

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
