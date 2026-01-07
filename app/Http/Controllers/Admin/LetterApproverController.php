<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterApprover;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LetterApproverController extends Controller
{

    /**
     * Display letter approvers list.
     */
    public function index(Request $request)
    {
        $unitId = $request->get('unit_id');

        $approvers = LetterApprover::with(['organizationUnit', 'user'])
            ->when($unitId, function ($q) use ($unitId) {
                if ($unitId === 'pusat') {
                    $q->whereNull('organization_unit_id');
                } else {
                    $q->where('organization_unit_id', $unitId);
                }
            })
            ->orderBy('organization_unit_id')
            ->orderBy('signer_type')
            ->paginate(20)
            ->withQueryString();

        $units = OrganizationUnit::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/LetterApprovers/Index', [
            'approvers' => $approvers,
            'units' => $units,
            'filters' => $request->only(['unit_id']),
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $units = OrganizationUnit::orderBy('name')->get(['id', 'name']);
        $users = User::with('role')
            ->whereHas('role', fn($q) => $q->whereIn('name', ['admin_unit', 'admin_pusat', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);

        return Inertia::render('Admin/LetterApprovers/Form', [
            'approver' => null,
            'units' => $units,
            'users' => $users,
            'signerTypes' => [
                ['value' => 'ketua', 'label' => 'Ketua'],
                ['value' => 'sekretaris', 'label' => 'Sekretaris'],
                ['value' => 'bendahara', 'label' => 'Bendahara'],
            ],
        ]);
    }

    /**
     * Store new approver.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'signer_type' => 'required|in:ketua,sekretaris,bendahara',
            'user_id' => 'required|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate
        $exists = LetterApprover::where('organization_unit_id', $validated['organization_unit_id'] ?? null)
            ->where('signer_type', $validated['signer_type'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['user_id' => 'Approver ini sudah terdaftar untuk unit dan tipe penandatangan yang sama.']);
        }

        LetterApprover::create([
            'organization_unit_id' => $validated['organization_unit_id'] ?? null,
            'signer_type' => $validated['signer_type'],
            'user_id' => $validated['user_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.letter-approvers.index')
            ->with('success', 'Approver berhasil ditambahkan');
    }

    /**
     * Show edit form.
     */
    public function edit(LetterApprover $letterApprover)
    {
        $units = OrganizationUnit::orderBy('name')->get(['id', 'name']);
        $users = User::with('role')
            ->whereHas('role', fn($q) => $q->whereIn('name', ['admin_unit', 'admin_pusat', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);

        return Inertia::render('Admin/LetterApprovers/Form', [
            'approver' => $letterApprover->load(['organizationUnit', 'user']),
            'units' => $units,
            'users' => $users,
            'signerTypes' => [
                ['value' => 'ketua', 'label' => 'Ketua'],
                ['value' => 'sekretaris', 'label' => 'Sekretaris'],
                ['value' => 'bendahara', 'label' => 'Bendahara'],
            ],
        ]);
    }

    /**
     * Update approver.
     */
    public function update(Request $request, LetterApprover $letterApprover)
    {
        $validated = $request->validate([
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'signer_type' => 'required|in:ketua,sekretaris,bendahara',
            'user_id' => 'required|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate (exclude current)
        $exists = LetterApprover::where('organization_unit_id', $validated['organization_unit_id'] ?? null)
            ->where('signer_type', $validated['signer_type'])
            ->where('user_id', $validated['user_id'])
            ->where('id', '!=', $letterApprover->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['user_id' => 'Approver ini sudah terdaftar untuk unit dan tipe penandatangan yang sama.']);
        }

        $letterApprover->update([
            'organization_unit_id' => $validated['organization_unit_id'] ?? null,
            'signer_type' => $validated['signer_type'],
            'user_id' => $validated['user_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.letter-approvers.index')
            ->with('success', 'Approver berhasil diperbarui');
    }

    /**
     * Delete approver.
     */
    public function destroy(LetterApprover $letterApprover)
    {
        $letterApprover->delete();

        return redirect()->route('admin.letter-approvers.index')
            ->with('success', 'Approver berhasil dihapus');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(LetterApprover $letterApprover)
    {
        $letterApprover->update([
            'is_active' => !$letterApprover->is_active,
        ]);

        return back()->with('success', 'Status approver berhasil diubah');
    }
}
