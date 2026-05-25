<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // GET /api/categories
    public function index()
    {
        $categories = Category::withCount('products')->get();

        return response()->json([
            'message'    => 'Daftar kategori',
            'categories' => $categories,
        ]);
    }

    // POST /api/categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message'  => 'Kategori berhasil dibuat',
            'category' => $category,
        ], 201);
    }

    // GET /api/categories/{id}
    public function show(Category $category)
    {
        $category->load('products');

        return response()->json([
            'message'  => 'Detail kategori',
            'category' => $category,
        ]);
    }

    // PUT /api/categories/{id}
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'message'  => 'Kategori berhasil diupdate',
            'category' => $category,
        ]);
    }

    // DELETE /api/categories/{id}
    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Kategori tidak bisa dihapus, masih ada produk terkait',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus',
        ]);
    }
}