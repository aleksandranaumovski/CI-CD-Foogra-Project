<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('restaurants')
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->ordered()
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json([
            'data' => new CategoryResource($category->loadCount('restaurants')),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'message' => 'Category created.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated.',
            'data' => new CategoryResource($category->fresh()->loadCount('restaurants')),
        ]);
    }

    /**
     * Refuses to delete a category that still has restaurants — the FK is
     * ON DELETE CASCADE, so this would silently take them with it.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $inUse = $category->restaurants()->count();

        if ($inUse > 0) {
            return response()->json([
                'message' => "This category still has {$inUse} restaurant(s). Move them first, or deactivate the category instead.",
            ], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    public function restore(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);

        $this->authorize('delete', $category);

        $category->restore();

        return response()->json([
            'message' => 'Category restored.',
            'data' => new CategoryResource($category),
        ]);
    }
}
