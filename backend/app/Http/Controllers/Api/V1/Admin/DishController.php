<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StoreDishRequest;
use App\Http\Requests\Menu\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Services\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function __construct(private readonly ImageStorage $images) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'menu_section_id' => ['nullable', 'integer', 'exists:menu_sections,id'],
        ]);

        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
        $this->authorize('view', $restaurant);

        $dishes = $restaurant->dishes()
            ->with('menuSection')
            ->when(
                isset($validated['menu_section_id']),
                fn ($q) => $q->where('menu_section_id', $validated['menu_section_id'])
            )
            ->orderBy('menu_section_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => DishResource::collection($dishes)]);
    }

    public function show(Dish $dish): JsonResponse
    {
        $this->authorize('view', $dish);

        return response()->json(['data' => new DishResource($dish->load('menuSection'))]);
    }

    public function store(StoreDishRequest $request): JsonResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->store($request->file('image'), 'dishes');
        }

        // restaurant_id is filled in by the Dish model from its parent section.
        $dish = Dish::create($data);

        return response()->json([
            'message' => 'Dish added to the menu.',
            'data' => new DishResource($dish->load('menuSection')),
        ], 201);
    }

    public function update(UpdateDishRequest $request, Dish $dish): JsonResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->replace(
                $request->file('image'), 'dishes', $dish->image_path
            );
        }

        $dish->update($data);

        return response()->json([
            'message' => 'Dish updated.',
            'data' => new DishResource($dish->fresh('menuSection')),
        ]);
    }

    public function destroy(Dish $dish): JsonResponse
    {
        $this->authorize('delete', $dish);

        $dish->delete();

        return response()->json(['message' => 'Dish removed from the menu.']);
    }

    /** Quick availability switch for the "sold out" case. */
    public function toggleAvailability(Dish $dish): JsonResponse
    {
        $this->authorize('update', $dish);

        $dish->update(['is_available' => ! $dish->is_available]);

        return response()->json([
            'message' => $dish->is_available ? 'Dish is back on the menu.' : 'Dish marked unavailable.',
            'data' => new DishResource($dish),
        ]);
    }
}
