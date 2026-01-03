<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MutationRequest;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use App\Services\NraGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Notifications\MutationApprovedNotification;
use App\Notifications\MutationRejectedNotification;

class MutationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', MutationRequest::class);

        $query = MutationRequest::with(['member', 'fromUnit', 'toUnit']);
        $user = $request->user();
        $unitId = $user->currentUnitId();

        // Apply unit scope via policy-based query filtering
        if (!$user->hasGlobalAccess()) {
            if ($unitId) {
                $query->where(function ($q) use ($unitId) {
                    $q->where('from_unit_id', $unitId)
                        ->orWhere('to_unit_id', $unitId);
                });
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $items = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Admin/Mutations/Index', [
            'items' => $items,
            'stats' => [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', MutationRequest::class);

        $user = $request->user();
        $unitId = $user->currentUnitId();
        $membersQuery = Member::select('id', 'full_name', 'nra', 'organization_unit_id')
            ->where('status', 'aktif');

        // Apply member scope
        if (!$user->hasGlobalAccess()) {
            if ($unitId) {
                $membersQuery->where('organization_unit_id', $unitId);
            } else {
                $membersQuery->whereRaw('1=0');
            }
        }
        $members = $membersQuery->orderBy('full_name')->get();

        return Inertia::render('Admin/Mutations/Create', [
            'units' => OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get(),
            'members' => $members,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'to_unit_id' => 'required|exists:organization_units,id',
            'reason' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $member = Member::findOrFail($validated['member_id']);

        // Use policy to check if user can create mutation for this member
        Gate::authorize('createFor', [MutationRequest::class, $member]);

        $path = $request->file('document') ? $request->file('document')->store('mutations', 'public') : null;

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $member->organization_unit_id,
            'to_unit_id' => $validated['to_unit_id'],
            'effective_date' => $validated['effective_date'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'document_path' => $path,
            'status' => 'pending',
            'submitted_by' => $request->user()->id,
        ]);

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'mutation_submitted',
            'subject_type' => MutationRequest::class,
            'subject_id' => $mutation->id,
            'payload' => ['member_id' => $member->id],
        ]);

        return redirect()->route('mutations.index')->with('success', 'Pengajuan mutasi dikirim');
    }

    public function show(MutationRequest $mutation)
    {
        Gate::authorize('view', $mutation);

        $mutation->load(['member', 'fromUnit', 'toUnit']);
        return Inertia::render('Admin/Mutations/Show', ['mutation' => $mutation]);
    }

    public function approve(Request $request, MutationRequest $mutation)
    {
        Gate::authorize('approve', $mutation);

        $mutation->status = 'approved';
        $mutation->approved_by = $request->user()->id;
        $mutation->save();

        $member = $mutation->member;
        $year = (int) now()->year;
        $gen = NraGenerator::generate($mutation->to_unit_id, $year);
        $member->organization_unit_id = $mutation->to_unit_id;
        $member->nra = $gen['nra'];
        $member->join_year = $year;
        $member->sequence_number = $gen['sequence'];
        $member->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'mutation_approved',
            'subject_type' => MutationRequest::class,
            'subject_id' => $mutation->id,
            'payload' => ['member_id' => $member->id, 'new_unit' => $mutation->to_unit_id],
        ]);

        $owner = $member->user_id ? \App\Models\User::find($member->user_id) : null;
        if ($owner) {
            $owner->notify(new MutationApprovedNotification($mutation));
        }

        return back()->with('success', 'Mutasi disetujui');
    }

    public function reject(Request $request, MutationRequest $mutation)
    {
        Gate::authorize('reject', $mutation);

        $mutation->status = 'rejected';
        $mutation->save();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'mutation_rejected',
            'subject_type' => MutationRequest::class,
            'subject_id' => $mutation->id,
        ]);

        $owner = $mutation->member?->user_id ? \App\Models\User::find($mutation->member->user_id) : null;
        if ($owner) {
            try {
                $owner->notify(new MutationRejectedNotification($mutation));
            } catch (\Throwable $e) {
            }
        }
        return back()->with('success', 'Mutasi ditolak');
    }
}
