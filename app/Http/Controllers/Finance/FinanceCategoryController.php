<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\OrganizationUnit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FinanceCategoryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FinanceCategory::class);

        $user = Auth::user();
        $isSuper = $user->hasRole('super_admin');
        $query = FinanceCategory::query()->with(['organizationUnit','creator']);

        $type = $request->query('type');
        $search = $request->query('search');
        $unitParam = $request->query('unit_id');

        if (!$isSuper) {
            $query->where('organization_unit_id', $user->organization_unit_id);
        } else {
            if ($unitParam === 'null') {
                $query->whereNull('organization_unit_id');
            } elseif ($unitParam) {
                $query->where('organization_unit_id', (int) $unitParam);
            }
        }

        if ($type && in_array($type, ['income','expense'])) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('name')->paginate(10)->withQueryString();

        $units = $isSuper ? OrganizationUnit::select('id','name')->orderBy('name')->get() : [];

        return Inertia::render('Finance/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['type','search','unit_id']),
            'units' => $units,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', FinanceCategory::class);

        $user = Auth::user();
        $units = $user->hasRole('super_admin') ? OrganizationUnit::select('id','name')->orderBy('name')->get() : [];

        return Inertia::render('Finance/Categories/Form', [
            'units' => $units,
            'category' => null,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', FinanceCategory::class);

        $user = Auth::user();
        $isSuper = $user->hasRole('super_admin');

        $type = $request->input('type');
        $name = $request->input('name');
        $unitId = $isSuper ? ($request->input('organization_unit_id') ? (int) $request->input('organization_unit_id') : null) : (int) $user->organization_unit_id;

        $validated = $request->validate([
            'name' => [
                'required','string','max:255',
                Rule::unique('finance_categories')->where(function($q) use ($unitId, $type){
                    return $q->where('organization_unit_id', $unitId)->where('type', $type);
                }),
            ],
            'type' => ['required', Rule::in(['income','expense'])],
            'description' => ['nullable','string'],
            'organization_unit_id' => ['nullable'],
        ]);

        $category = FinanceCategory::create([
            'organization_unit_id' => $unitId,
            'name' => $name,
            'type' => $type,
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'finance_category_created',
            'subject_type' => FinanceCategory::class,
            'subject_id' => $category->id,
            'payload' => ['name' => $category->name, 'type' => $category->type, 'unit_id' => $category->organization_unit_id],
        ]);

        return redirect()->route('finance.categories.index')->with('success', 'Kategori berhasil dibuat');
    }

    public function edit(FinanceCategory $category)
    {
        Gate::authorize('update', $category);

        $user = Auth::user();
        $units = $user->hasRole('super_admin') ? OrganizationUnit::select('id','name')->orderBy('name')->get() : [];

        return Inertia::render('Finance/Categories/Form', [
            'units' => $units,
            'category' => $category->only(['id','name','type','description','organization_unit_id']),
        ]);
    }

    public function update(Request $request, FinanceCategory $category)
    {
        Gate::authorize('update', $category);

        $user = Auth::user();
        $isSuper = $user->hasRole('super_admin');

        $type = $request->input('type');
        $name = $request->input('name');
        $unitId = $isSuper ? ($request->input('organization_unit_id') ? (int) $request->input('organization_unit_id') : null) : (int) $user->organization_unit_id;

        $validated = $request->validate([
            'name' => [
                'required','string','max:255',
                Rule::unique('finance_categories')->where(function($q) use ($unitId, $type){
                    return $q->where('organization_unit_id', $unitId)->where('type', $type);
                })->ignore($category->id),
            ],
            'type' => ['required', Rule::in(['income','expense'])],
            'description' => ['nullable','string'],
            'organization_unit_id' => [$isSuper ? 'nullable' : 'required'],
        ]);

        $category->update([
            'organization_unit_id' => $unitId,
            'name' => $name,
            'type' => $type,
            'description' => $validated['description'] ?? null,
        ]);

        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'finance_category_updated',
            'subject_type' => FinanceCategory::class,
            'subject_id' => $category->id,
            'payload' => ['name' => $category->name, 'type' => $category->type, 'unit_id' => $category->organization_unit_id],
        ]);

        return redirect()->route('finance.categories.index')->with('success', 'Kategori berhasil diperbarui');
    }

    public function destroy(FinanceCategory $category)
    {
        Gate::authorize('delete', $category);
        $deletedPayload = ['name' => $category->name, 'type' => $category->type, 'unit_id' => $category->organization_unit_id];
        $category->delete();
        ActivityLog::create([
            'actor_id' => Auth::id(),
            'action' => 'finance_category_deleted',
            'subject_type' => FinanceCategory::class,
            'subject_id' => $category->id,
            'payload' => $deletedPayload,
        ]);
        return redirect()->route('finance.categories.index')->with('success', 'Kategori berhasil dihapus');
    }

    public function export(Request $request)
    {
        Gate::authorize('viewAny', FinanceCategory::class);

        $user = Auth::user();
        $isSuper = $user->hasRole('super_admin');
        $query = FinanceCategory::query()->with(['organizationUnit','creator']);

        $type = $request->query('type');
        $search = $request->query('search');
        $unitParam = $request->query('unit_id');

        if (!$isSuper) {
            $query->where('organization_unit_id', $user->organization_unit_id);
        } else {
            if ($unitParam === 'null') {
                $query->whereNull('organization_unit_id');
            } elseif ($unitParam) {
                $query->where('organization_unit_id', (int) $unitParam);
            }
        }

        if ($type && in_array($type, ['income','expense'])) {
            $query->where('type', $type);
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $filename = 'finance_categories_' . now()->format('Ymd_His') . '.csv';
        return response()->streamDownload(function() use ($query){
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama','Tipe','Unit','Dibuat Oleh']);
            $query->orderBy('name')->chunk(500, function($rows) use (&$out){
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->name,
                        $c->type,
                        $c->organizationUnit?->name ?? 'Global',
                        $c->creator?->name ?? '-',
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
