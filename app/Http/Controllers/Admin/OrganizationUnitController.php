<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;

class OrganizationUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', OrganizationUnit::class);

        $query = OrganizationUnit::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $units = $query->orderBy('code')->paginate(10)->withQueryString();

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', OrganizationUnit::class);
        return Inertia::render('Admin/Units/Form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', OrganizationUnit::class);

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:organization_units,code|regex:/^[0-9]+$/',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|min:10',
        ]);

        OrganizationUnit::create($validated);

        return redirect()->route('admin.units.index')->with('success', 'Data berhasil dibuat');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OrganizationUnit $unit)
    {
        Gate::authorize('update', $unit);
        return Inertia::render('Admin/Units/Form', [
            'unit' => $unit,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OrganizationUnit $unit)
    {
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'code' => 'required|string|size:3|regex:/^[0-9]+$/|unique:organization_units,code,' . $unit->id,
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|min:10',
        ]);

        $unit->update($validated);

        return redirect()->route('admin.units.index')->with('success', 'Perubahan berhasil disimpan');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrganizationUnit $unit)
    {
        Gate::authorize('delete', $unit);
        $unit->delete();

        return redirect()->route('admin.units.index')->with('success', 'Data berhasil dihapus');
    }
}
