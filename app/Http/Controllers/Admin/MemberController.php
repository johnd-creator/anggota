<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Member::class);

        $query = Member::query()->with('unit');
        $user = $request->user();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($statuses = $request->get('statuses')) {
            $values = is_array($statuses) ? $statuses : [$statuses];
            $values = array_filter($values); // remove empty
            if (!empty($values)) {
                $query->whereIn('status', $values);
            }
        } elseif ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // admin_unit only sees their own unit, global access roles see all
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id) {
                $query->where('organization_unit_id', $user->organization_unit_id);
            } else {
                $query->whereRaw('1=0');
            }
        } elseif (!$user->hasGlobalAccess()) {
            // Non-admin roles shouldn't access this, but if they do, limit by unit
            if ($user->organization_unit_id) {
                $query->where('organization_unit_id', $user->organization_unit_id);
            }
        } else {
            // Global access - can filter by units if specified
            if ($units = $request->get('units')) {
                $ids = is_array($units) ? $units : [$units];
                $query->whereIn('organization_unit_id', $ids);
            }
        }

        $sort = $request->get('sort'); // name|status|join_date
        $dir = $request->get('dir', 'asc');
        if (in_array($sort, ['name', 'status', 'join_date'])) {
            $columnMap = [
                'name' => 'full_name',
                'status' => 'status',
                'join_date' => 'join_date',
            ];
            $query->orderBy($columnMap[$sort], $dir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderByRaw('kta_number IS NULL, kta_number ASC');
        }

        $members = $query->select(['id', 'full_name', 'email', 'phone', 'status', 'organization_unit_id', 'nra', 'kta_number', 'nip', 'union_position_id', 'birth_date', 'join_date'])
            ->with('unionPosition')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status', 'statuses', 'units', 'sort', 'dir']),
            'units' => \Illuminate\Support\Facades\Cache::remember('units_select_options', 300, fn() => OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get()),
            'admin_unit_id' => $user && $user->role && $user->role->name === 'admin_unit' ? $user->organization_unit_id : null,
            'admin_unit_missing' => $user && $user->role && $user->role->name === 'admin_unit' && !$user->organization_unit_id,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Member::class);

        return Inertia::render('Admin/Members/Form', [
            'units' => OrganizationUnit::select('id', 'name', 'code')->orderBy('code')->get(),
            'positions' => \App\Models\UnionPosition::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Member::class);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'email' => 'required|email|unique:members,email',
            'nip' => 'required|alpha_num|max:50',
            'union_position_id' => 'required|exists:union_positions,id',
            'phone' => ['nullable', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'employment_type' => 'required|in:organik,tkwt',
            'status' => 'required|in:aktif,cuti,suspended,resign,pensiun',
            'join_date' => 'required|date',
            'organization_unit_id' => 'required|exists:organization_units,id',
            'notes' => 'nullable|string',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        // admin_unit can only create members in their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id) {
                $validated['organization_unit_id'] = $user->organization_unit_id;
            }
        }
        // admin_pusat and super_admin can choose any unit

        $joinYear = (int) date('Y', strtotime($validated['join_date']));
        $unitId = (int) $validated['organization_unit_id'];
        $gen = NraGenerator::generate($unitId, $joinYear);
        $kta = \App\Services\KtaGenerator::generate($unitId, $joinYear);

        $member = Member::create([
            ...$validated,
            'nra' => $gen['nra'],
            'join_year' => $joinYear,
            'sequence_number' => $gen['sequence'],
            'kta_number' => $kta['kta'],
        ]);

        if ($request->filled('user_id')) {
            $user = \App\Models\User::find((int) $request->input('user_id'));
            if ($user)
                $user->assignMember($member);
        }

        if ($request->file('photo')) {
            $path = $request->file('photo')->store('members/photos', 'public');
            $member->photo_path = $path;
            $member->save();
        }

        if ($request->file('documents')) {
            foreach ($request->file('documents') as $doc) {
                $path = $doc->store('members/documents', 'public');
                MemberDocument::create([
                    'member_id' => $member->id,
                    'type' => $doc->getClientOriginalExtension(),
                    'path' => $path,
                    'original_name' => $doc->getClientOriginalName(),
                    'size' => $doc->getSize(),
                ]);
            }
        }

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'member_created',
            'subject_type' => Member::class,
            'subject_id' => $member->id,
            'payload' => ['nra' => $member->nra],
        ]);

        return redirect()->route('admin.members.index')->with('success', 'Data berhasil dibuat');
    }

    public function show(Member $member)
    {
        Gate::authorize('view', $member);
        $user = request()->user();
        // admin_unit can only view members in their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id !== $member->organization_unit_id) {
                abort(403);
            }
        }
        // admin_pusat and super_admin can view any member
        $member->load(['unit', 'documents', 'statusLogs', 'unionPosition']);
        ActivityLog::create([
            'actor_id' => Auth::id(),
            'action' => 'member_viewed',
            'subject_type' => Member::class,
            'subject_id' => $member->id,
            'payload' => ['nra' => $member->nra],
        ]);
        return Inertia::render('Admin/Members/Show', [
            'member' => $member,
        ]);
    }

    public function edit(Member $member)
    {
        Gate::authorize('update', $member);
        $user = request()->user();
        // admin_unit can only edit members in their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id !== $member->organization_unit_id) {
                abort(403);
            }
        }
        // admin_pusat and super_admin can edit any member
        return Inertia::render('Admin/Members/Form', [
            'member' => $member,
            'units' => OrganizationUnit::select('id', 'name', 'code')->orderBy('code')->get(),
            'positions' => \App\Models\UnionPosition::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Member $member)
    {
        Gate::authorize('update', $member);
        $user = $request->user();
        // admin_unit can only update members in their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            if ($user->organization_unit_id !== $member->organization_unit_id) {
                abort(403);
            }
        }
        // admin_pusat and super_admin can update any member

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'email' => 'required|email|unique:members,email,' . $member->id,
            'kta_number' => ['nullable', 'regex:/^\d{3}-SPPIPS-\d{2}\d{3}$/', 'unique:members,kta_number,' . $member->id],
            'nip' => 'required|alpha_num|max:50',
            'union_position_id' => 'required|exists:union_positions,id',
            'phone' => ['nullable', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'employment_type' => 'required|in:organik,tkwt',
            'status' => 'required|in:aktif,cuti,suspended,resign,pensiun',
            'join_date' => 'required|date',
            'organization_unit_id' => 'required|exists:organization_units,id',
            'notes' => 'nullable|string',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);
        try {
            if ($user && $user->role && $user->role->name === 'admin_unit' && $user->organization_unit_id) {
                $validated['organization_unit_id'] = $user->organization_unit_id;
            }

            $member->update($validated);

            if ($request->file('photo')) {
                $path = $request->file('photo')->store('members/photos', 'public');
                $member->photo_path = $path;
                $member->save();
            }

            if ($request->file('documents')) {
                foreach ($request->file('documents') as $doc) {
                    $path = $doc->store('members/documents', 'public');
                    MemberDocument::create([
                        'member_id' => $member->id,
                        'type' => $doc->getClientOriginalExtension(),
                        'path' => $path,
                        'original_name' => $doc->getClientOriginalName(),
                        'size' => $doc->getSize(),
                    ]);
                }
            }

            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'member_updated',
                'subject_type' => Member::class,
                'subject_id' => $member->id,
                'payload' => ['nra' => $member->nra],
            ]);

            return redirect()->route('admin.members.show', $member)->with('success', 'Perubahan berhasil disimpan');
        } catch (\Throwable $e) {
            Log::error('Member update failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['general' => 'Terjadi kesalahan pada server'])
                ->with('error', 'Terjadi kesalahan pada server')
                ->withInput();
        }
    }
}
