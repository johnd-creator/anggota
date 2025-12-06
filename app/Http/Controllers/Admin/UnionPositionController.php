<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnionPosition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnionPositionController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->query('search');
        $query = UnionPosition::query();
        if ($search) {
            $query->where(function($q) use ($search){
                $q->where('name','like',"%{$search}%")->orWhere('code','like',"%{$search}%");
            });
        }
        $items = $query->orderBy('name')->paginate(10)->withQueryString();
        return Inertia::render('Admin/UnionPositions/Index', [
            'items' => $items,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/UnionPositions/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:union_positions,name',
            'code' => 'nullable|string|max:50|unique:union_positions,code',
            'description' => 'nullable|string',
        ]);
        UnionPosition::create($validated);
        return redirect()->route('admin.union_positions.index')->with('success','Jabatan serikat ditambahkan');
    }

    public function edit(UnionPosition $unionPosition)
    {
        return Inertia::render('Admin/UnionPositions/Form', [ 'item' => $unionPosition ]);
    }

    public function update(Request $request, UnionPosition $unionPosition)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:union_positions,name,' . $unionPosition->id,
            'code' => 'nullable|string|max:50|unique:union_positions,code,' . $unionPosition->id,
            'description' => 'nullable|string',
        ]);
        $unionPosition->update($validated);
        return redirect()->route('admin.union_positions.index')->with('success','Jabatan serikat diperbarui');
    }

    public function destroy(UnionPosition $unionPosition)
    {
        $unionPosition->delete();
        return redirect()->route('admin.union_positions.index')->with('success','Jabatan serikat dihapus');
    }
}

