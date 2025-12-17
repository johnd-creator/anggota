<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

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

        $validated = $request->validate($this->validationRules());
        $validated['abbreviation'] = strtoupper($validated['abbreviation']);

        try {
            OrganizationUnit::create($validated);
        } catch (QueryException $e) {
            if ($this->looksLikeMissingColumns($e)) {
                return redirect()
                    ->back()
                    ->with('error', 'Database belum ter-migrate untuk field Unit terbaru. Jalankan `php artisan migrate` lalu coba lagi.');
            }
            throw $e;
        }

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

        $validated = $request->validate($this->validationRules($unit->id));
        $validated['abbreviation'] = strtoupper($validated['abbreviation']);

        try {
            $unit->update($validated);
        } catch (QueryException $e) {
            if ($this->looksLikeMissingColumns($e)) {
                return redirect()
                    ->back()
                    ->with('error', 'Database belum ter-migrate untuk field Unit terbaru. Jalankan `php artisan migrate` lalu coba lagi.');
            }
            throw $e;
        }

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

    /**
     * Get validation rules for store/update.
     */
    protected function validationRules($unitId = null): array
    {
        $codeUnique = $unitId
            ? 'unique:organization_units,code,' . $unitId
            : 'unique:organization_units,code';

        $abbrUnique = $unitId
            ? 'unique:organization_units,abbreviation,' . $unitId
            : 'unique:organization_units,abbreviation';

        return [
            // Basic fields
            'code' => ['required', 'string', 'min:1', 'max:3', 'regex:/^[A-Za-z0-9]+$/', $codeUnique],
            'name' => 'required|string|max:255',
            'organization_type' => ['required', Rule::in(['DPP', 'DPD'])],
            'abbreviation' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Za-z0-9]+$/', $abbrUnique],
            'address' => 'nullable|string|min:10',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',

            // Letterhead fields - all nullable
            'letterhead_name' => 'nullable|string|max:255',
            'letterhead_address' => 'nullable|string|max:500',
            'letterhead_city' => 'nullable|string|max:100',
            'letterhead_postal_code' => 'nullable|string|max:20',
            'letterhead_phone' => 'nullable|string|max:50',
            'letterhead_email' => 'nullable|email|max:255',
            'letterhead_website' => 'nullable|url|max:255',
            'letterhead_fax' => 'nullable|string|max:50',
            'letterhead_whatsapp' => 'nullable|string|max:50',
            'letterhead_footer_text' => 'nullable|string|max:500',
            'letterhead_logo_path' => 'nullable|string|max:255',
        ];
    }

    private function looksLikeMissingColumns(QueryException $e): bool
    {
        $msg = strtolower($e->getMessage());
        // SQLite: "no such column", MySQL: "unknown column"
        if (!str_contains($msg, 'no such column') && !str_contains($msg, 'unknown column')) {
            return false;
        }

        return str_contains($msg, 'organization_type')
            || str_contains($msg, 'abbreviation')
            || str_contains($msg, 'phone')
            || str_contains($msg, 'email');
    }
}
