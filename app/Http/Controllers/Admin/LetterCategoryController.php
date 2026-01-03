<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LetterCategoryController extends Controller
{
    protected array $allowedColors = [
        'neutral',
        'blue',
        'cyan',
        'indigo',
        'green',
        'amber',
        'red',
        'purple',
        'teal',
    ];

    /**
     * Display a listing of letter categories.
     */
    public function index()
    {
        $categories = LetterCategory::withCount('letters')
            ->ordered()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/LetterCategories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        return Inertia::render('Admin/LetterCategories/Form', [
            'category' => null,
            'allowedColors' => $this->allowedColors,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        // Transform code to uppercase before validation
        $request->merge([
            'code' => strtoupper(str_replace(' ', '_', $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => [
                'required',
                'string',
                'max:10',
                'unique:letter_categories,code',
                'regex:/^[A-Z0-9_]+$/',
            ],
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', Rule::in($this->allowedColors)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => 'boolean',
            // Template fields
            'template_subject' => 'nullable|string|max:5000',
            'template_body' => 'nullable|string|max:20000',
            'template_cc_text' => 'nullable|string|max:5000',
            'default_confidentiality' => ['nullable', Rule::in(['biasa', 'terbatas', 'rahasia'])],
            'default_urgency' => ['nullable', Rule::in(['biasa', 'segera', 'kilat'])],
            'default_signer_type' => ['nullable', Rule::in(['ketua', 'sekretaris'])],
        ], [
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan underscore.',
            'code.unique' => 'Kode sudah digunakan.',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['color'] = $validated['color'] ?? 'neutral';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        LetterCategory::create($validated);

        return redirect()->route('admin.letter-categories.index')
            ->with('success', 'Kategori surat berhasil ditambahkan');
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(LetterCategory $letterCategory)
    {
        return Inertia::render('Admin/LetterCategories/Form', [
            'category' => $letterCategory,
            'allowedColors' => $this->allowedColors,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, LetterCategory $letterCategory)
    {
        // Transform code to uppercase before validation
        $request->merge([
            'code' => strtoupper(str_replace(' ', '_', $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('letter_categories', 'code')->ignore($letterCategory->id),
                'regex:/^[A-Z0-9_]+$/',
            ],
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', Rule::in($this->allowedColors)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => 'boolean',
            // Template fields
            'template_subject' => 'nullable|string|max:5000',
            'template_body' => 'nullable|string|max:20000',
            'template_cc_text' => 'nullable|string|max:5000',
            'default_confidentiality' => ['nullable', Rule::in(['biasa', 'terbatas', 'rahasia'])],
            'default_urgency' => ['nullable', Rule::in(['biasa', 'segera', 'kilat'])],
            'default_signer_type' => ['nullable', Rule::in(['ketua', 'sekretaris'])],
        ], [
            'code.regex' => 'Kode hanya boleh berisi huruf kapital, angka, dan underscore.',
            'code.unique' => 'Kode sudah digunakan.',
        ]);

        $validated['color'] = $validated['color'] ?? ($letterCategory->color ?? 'neutral');
        $validated['sort_order'] = $validated['sort_order'] ?? ($letterCategory->sort_order ?? 0);

        $letterCategory->update($validated);

        return redirect()->route('admin.letter-categories.index')
            ->with('success', 'Kategori surat berhasil diperbarui');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(LetterCategory $letterCategory)
    {
        // Check if category has letters
        if ($letterCategory->letters()->exists()) {
            return back()->withErrors([
                'category' => 'Kategori tidak dapat dihapus karena masih memiliki surat terkait.',
            ]);
        }

        $letterCategory->delete();

        return redirect()->route('admin.letter-categories.index')
            ->with('success', 'Kategori surat berhasil dihapus');
    }
}
