<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        $unitId = $user?->currentUnitId();

        $query = Announcement::query()
            ->with(['creator', 'organizationUnit'])
            ->latest();

        // 1. Scoping query based on what user can MANAGE (manageable)
        if ($user->role?->name === 'admin_unit') {
            $query->where('scope_type', 'unit')
                ->where('organization_unit_id', $unitId);
        }
        // Super/Pusat can see all provided they are manageable. 
        // If Admin Pusat is restricted to Global only by policy, we filter here.
        // Current Policy 'update' allows Super/Pusat to update anything. So we show everything.

        // 2. Filters
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active')
                $query->where('is_active', true);
            if ($status === 'inactive')
                $query->where('is_active', false);
        }

        if ($scope = $request->input('scope_type')) {
            if ($scope !== 'all')
                $query->where('scope_type', $scope);
        }

        if ($pinned = $request->input('pinned')) {
            if ($pinned === 'pinned')
                $query->where('pin_to_dashboard', true);
            if ($pinned === 'not_pinned')
                $query->where('pin_to_dashboard', false);
        }

        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'scope_type', 'pinned']),
            'can' => [
                'create' => Gate::allows('create', Announcement::class),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Announcement::class);

        $user = auth()->user();
        $isGlobal = in_array($user->role?->name, ['super_admin', 'admin_pusat']);
        $unitId = $user?->currentUnitId();

        // Determine allowed scopes
        $allowedScopes = [];
        if ($isGlobal) {
            $allowedScopes[] = ['value' => 'global_all', 'label' => 'Global (Semua User)'];
            $allowedScopes[] = ['value' => 'global_officers', 'label' => 'Global (Hanya Pengurus)'];
            // Optional: Allow global admin to create unit announcement
            $allowedScopes[] = ['value' => 'unit', 'label' => 'Unit Organisasi'];
        } else {
            // Admin Unit
            $allowedScopes[] = ['value' => 'unit', 'label' => 'Unit Organisasi'];
        }

        $units = $isGlobal
            ? OrganizationUnit::select('id', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Admin/Announcements/Form', [
            'mode' => 'create',
            'allowed_scopes' => $allowedScopes,
            'units' => $units,
            'defaults' => [
                'is_active' => true,
                'pin_to_dashboard' => false,
                'scope_type' => $isGlobal ? 'global_all' : 'unit',
                'organization_unit_id' => $isGlobal ? null : $unitId,
            ]
        ]);
    }

    public function store(AnnouncementRequest $request)
    {
        Gate::authorize('create', Announcement::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();

        Announcement::create($data);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function edit(Announcement $announcement)
    {
        Gate::authorize('update', $announcement);

        $announcement->load('attachments');

        $user = auth()->user();
        $isGlobal = in_array($user->role?->name, ['super_admin', 'admin_pusat']);

        $allowedScopes = [];
        if ($isGlobal) {
            $allowedScopes[] = ['value' => 'global_all', 'label' => 'Global (Semua User)'];
            $allowedScopes[] = ['value' => 'global_officers', 'label' => 'Global (Hanya Pengurus)'];
            $allowedScopes[] = ['value' => 'unit', 'label' => 'Unit Organisasi'];
        } else {
            $allowedScopes[] = ['value' => 'unit', 'label' => 'Unit Organisasi'];
        }

        $units = $isGlobal
            ? OrganizationUnit::select('id', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Admin/Announcements/Form', [
            'mode' => 'edit',
            'announcement' => $announcement,
            'allowed_scopes' => $allowedScopes,
            'units' => $units,
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        Gate::authorize('update', $announcement);

        $announcement->update($request->validated());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement)
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function toggleActive(Announcement $announcement)
    {
        Gate::authorize('update', $announcement);

        $announcement->update(['is_active' => !$announcement->is_active]);

        return back()->with('success', 'Status pengumuman diperbarui.');
    }

    public function togglePin(Announcement $announcement)
    {
        Gate::authorize('update', $announcement);

        $announcement->update(['pin_to_dashboard' => !$announcement->pin_to_dashboard]);

        return back()->with('success', 'Status pin diperbarui.');
    }
}
