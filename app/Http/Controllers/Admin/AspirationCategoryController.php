<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AspirationCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AspirationCategoryController extends Controller
{
    public function index()
    {
        $categories = AspirationCategory::withCount('aspirations')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Aspirations/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:aspiration_categories,name',
            'description' => 'nullable|string|max:500',
        ]);

        AspirationCategory::create($validated);

        return back()->with('success', 'Kategori berhasil ditambahkan');
    }

    public function update(Request $request, AspirationCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:aspiration_categories,name,' . $category->id,
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori berhasil diperbarui');
    }

    public function destroy(AspirationCategory $category)
    {
        if ($category->aspirations()->exists()) {
            return back()->withErrors(['category' => 'Kategori tidak dapat dihapus karena masih memiliki aspirasi']);
        }

        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus');
    }
}
