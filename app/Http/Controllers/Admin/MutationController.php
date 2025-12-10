<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MutationRequest;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use App\Services\NraGenerator;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MutationController extends Controller
{
    public function index(Request $request)
    {
        $query = MutationRequest::with(['member', 'fromUnit', 'toUnit']);
        $user = $request->user();
        // admin_unit sees only their unit; admin_pusat and super_admin see all
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id) {
                $query->where(function ($q) use ($user) {
                    $q->where('from_unit_id', $user->organization_unit_id)
                        ->orWhere('to_unit_id', $user->organization_unit_id);
                });
            } else {
                $query->whereRaw('1=0');
            }
        }
        // admin_pusat and super_admin have global access - no unit filter
        if ($status = $request->get('status'))
            $query->where('status', $status);
        $items = $query->latest()->paginate(10)->withQueryString();
        $membersQuery = Member::select('id', 'full_name', 'nra', 'organization_unit_id')
            ->where('status', 'aktif');
        // admin_unit only sees members from their unit; admin_pusat/super_admin see all
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id) {
                $membersQuery->where('organization_unit_id', $user->organization_unit_id);
            } else {
                $membersQuery->whereRaw('1=0');
            }
        }
        $members = $membersQuery->orderBy('full_name')->get();
        return Inertia::render('Admin/Mutations/Index', [
            'items' => $items,
            'units' => OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get(),
            'members' => $members,
            'selected_member' => (int) $request->query('member_id'),
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
        $user = $request->user();
        // admin_unit can only mutate members from their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if (!$user->organization_unit_id || $user->organization_unit_id !== $member->organization_unit_id) {
                abort(403);
            }
        }
        // admin_pusat and super_admin can mutate any member
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

        return back()->with('success', 'Pengajuan mutasi dikirim');
    }

    public function show(MutationRequest $mutation)
    {
        $mutation->load(['member', 'fromUnit', 'toUnit']);
        $user = request()->user();
        // admin_unit can only view mutations involving their unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if (!$user->organization_unit_id || ($mutation->from_unit_id !== $user->organization_unit_id && $mutation->to_unit_id !== $user->organization_unit_id)) {
                abort(403);
            }
        }
        // admin_pusat and super_admin can view any mutation
        return Inertia::render('Admin/Mutations/Show', ['mutation' => $mutation]);
    }

    public function approve(Request $request, MutationRequest $mutation)
    {
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
            $owner->notify(new \App\Notifications\MutationApprovedNotification($mutation));
        }

        return back()->with('success', 'Mutasi disetujui');
    }

    public function reject(Request $request, MutationRequest $mutation)
    {
        $mutation->status = 'rejected';
        $mutation->save();
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'mutation_rejected',
            'subject_type' => MutationRequest::class,
            'subject_id' => $mutation->id,
        ]);
        return back()->with('success', 'Mutasi ditolak');
    }
}
