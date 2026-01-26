<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\OrganizationUnit;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Member::class);

        $query = Member::query()->with('unit');
        $user = $request->user();
        $isGlobal = $user?->hasGlobalAccess() ?? false;
        $unitId = $user?->currentUnitId();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($statuses = $request->get('statuses')) {
            $values = is_array($statuses) ? $statuses : [$statuses];
            $values = array_filter($values); // remove empty
            if (! empty($values)) {
                $query->whereIn('status', $values);
            }
        } elseif ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // admin_unit only sees their own unit, global access roles see all
        if (! $isGlobal) {
            // Non-global users must be scoped to their effective unit
            if ($unitId) {
                $query->where('organization_unit_id', $unitId);
            } else {
                $query->whereRaw('1=0');
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

        $units = [];
        if ($isGlobal) {
            $units = \Illuminate\Support\Facades\Cache::remember('units_select_options:global', 300, fn () => OrganizationUnit::select('id', 'name', 'code')->orderBy('name')->get());
        } elseif ($unitId) {
            $units = \Illuminate\Support\Facades\Cache::remember("units_select_options:unit:{$unitId}", 300, fn () => OrganizationUnit::where('id', $unitId)->select('id', 'name', 'code')->get());
        }

        $adminUnitId = $user?->hasRole('admin_unit') ? $unitId : null;

        return Inertia::render('Admin/Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status', 'statuses', 'units', 'sort', 'dir']),
            'units' => $units,
            'admin_unit_id' => $adminUnitId,
            'admin_unit_missing' => $user?->hasRole('admin_unit') && ! $unitId,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Member::class);
        $user = request()->user();
        $unitId = $user?->currentUnitId();

        // admin_unit can only see their own unit
        if ($user?->hasRole('admin_unit')) {
            $units = $unitId ? OrganizationUnit::where('id', $unitId)->select('id', 'name', 'code')->get() : collect([]);
        } else {
            $units = OrganizationUnit::select('id', 'name', 'code')->orderBy('code')->get();
        }

        return Inertia::render('Admin/Members/Form', [
            'units' => $units,
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
            'phone' => ['nullable', 'regex:/^(\+62|62)8\d{7,11}$|^0[8-9]\d{7,11}$/'],
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'employment_type' => 'required|in:organik,tkwt',
            'status' => 'required|in:aktif,cuti,suspended,resign,pensiun',
            'join_date' => 'required|date',
            'company_join_date' => 'nullable|date',
            'organization_unit_id' => 'required|exists:organization_units,id',
            'notes' => 'nullable|string',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        // admin_unit can only create members in their own unit
        if ($user && $user->role && $user->role->name === 'admin_unit') {
            $currentUnitId = $user->currentUnitId();
            if ($currentUnitId) {
                $validated['organization_unit_id'] = $currentUnitId;
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
            if ($user) {
                // Update user company_email if provided
                if ($request->filled('company_email')) {
                    $user->company_email = $request->input('company_email');
                    $user->save();
                }
                $user->assignMember($member);
            }
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

        $member->load('user');

        $user = request()->user();
        $unitId = $user?->currentUnitId();
        $units = ($user?->hasRole('admin_unit'))
            ? ($unitId ? OrganizationUnit::where('id', $unitId)->select('id', 'name', 'code')->get() : collect([]))
            : OrganizationUnit::select('id', 'name', 'code')->orderBy('code')->get();

        return Inertia::render('Admin/Members/Form', [
            'member' => $member,
            'units' => $units,
            'positions' => \App\Models\UnionPosition::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Member $member)
    {
        Gate::authorize('update', $member);
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'email' => 'required|email|unique:members,email,'.$member->id,
            'kta_number' => ['nullable', 'regex:/^\d{3}-SPPIPS-\d{2}\d{3}$/', 'unique:members,kta_number,'.$member->id],
            'nip' => 'required|alpha_num|max:50',
            'union_position_id' => 'required|exists:union_positions,id',
            'phone' => ['nullable', 'regex:/^(\+62|62)8\d{7,11}$|^0[8-9]\d{7,11}$/'],
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'employment_type' => 'required|in:organik,tkwt',
            'status' => 'required|in:aktif,cuti,suspended,resign,pensiun',
            'join_date' => 'required|date',
            'company_join_date' => 'nullable|date',
            'organization_unit_id' => 'required|exists:organization_units,id',
            'notes' => 'nullable|string',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);
        try {
            if ($user?->hasRole('admin_unit')) {
                $currentUnitId = $user->currentUnitId();
                if ($currentUnitId) {
                    $validated['organization_unit_id'] = $currentUnitId;
                }
            }

            $member->update($validated);

            // Sync company_email to linked user
            if ($member->user) {
                $member->user->company_email = $request->input('company_email');
                $member->user->save();
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

    public function searchByPhoneOrNip(Request $request)
    {
        $allowedRoles = ['admin_unit', 'super_admin', 'admin_pusat', 'bendahara', 'pengurus'];
        if (! $request->user()?->hasAnyRole($allowedRoles)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'q' => 'required|string|min:3|max:50',
            'limit' => 'integer|min:1|max:20',
        ]);

        $query = trim($request->input('q'));
        $limit = $request->input('limit', 10);

        $user = $request->user();
        $unitId = $user?->currentUnitId();
        $isGlobal = $user?->hasGlobalAccess() ?? false;

        $queryBuilder = Member::query()
            ->where(function ($q) use ($query) {
                $q->where('phone', 'like', "%{$query}%")
                    ->orWhere('nip', 'like', "%{$query}%");
            })
            ->select(['id', 'full_name', 'nra', 'kta_number', 'nip', 'phone', 'photo_path'])
            ->with('unit:id,name,code');

        if (! $isGlobal) {
            if ($unitId) {
                $queryBuilder->where('organization_unit_id', $unitId);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
            }
        }

        $members = $queryBuilder->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $members,
            'count' => $members->count(),
        ]);
    }
}
