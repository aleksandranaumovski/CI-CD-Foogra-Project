<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /** Feeds the home page's "Popular Categories" carousel. */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->withCount([
                'restaurants' => fn (Builder $q) => $q->where('status', RestaurantStatus::Published),
            ])
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function show(Category $category): JsonResponse
    {
        $category->loadCount([
            'restaurants' => fn (Builder $q) => $q->where('status', RestaurantStatus::Published),
        ]);

        return response()->json(['data' => new CategoryResource($category)]);
    }
}
