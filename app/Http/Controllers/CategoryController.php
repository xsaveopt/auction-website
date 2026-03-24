<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'categories' => $categories->map(fn(Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'sort_order' => $c->sort_order,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{name: string, sort_order?: int} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($validated['name']);
        $original = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter++;
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'category.create', $category, ['name' => $category->name]);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort_order' => $category->sort_order,
            ],
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        /** @var array{name: string, sort_order?: int} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($validated['name']);
        $original = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
            $slug = $original . '-' . $counter++;
        }

        $oldName = $category->name;
        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'category.update', $category, [
            'old_name' => $oldName,
            'name' => $category->name,
        ]);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort_order' => $category->sort_order,
            ],
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'category.delete', $category, ['name' => $category->name]);

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
