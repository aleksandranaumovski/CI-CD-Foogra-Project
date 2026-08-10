<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StoreMenuSectionRequest;
use App\Http\Requests\Menu\UpdateMenuSectionRequest;
use App\Http\Resources\MenuSectionResource;
use App\Models\MenuSection;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
        ]);

        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
        $this->authorize('view', $restaurant);

        $sections = $restaurant->menuSections()
            ->with('dishes')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => MenuSectionResource::collection($sections)]);
    }

    public function show(MenuSection $menuSection): JsonResponse
    {
        $this->authorize('view', $menuSection);

        return response()->json([
            'data' => new MenuSectionResource($menuSection->load('dishes')),
        ]);
    }

    public function store(StoreMenuSectionRequest $request): JsonResponse
    {
        $section = MenuSection::create($request->validated());

        return response()->json([
            'message' => 'Menu section created.',
            'data' => new MenuSectionResource($section),
        ], 201);
    }

    public function update(UpdateMenuSectionRequest $request, MenuSection $menuSection): JsonResponse
    {
        $menuSection->update($request->validated());

        return response()->json([
            'message' => 'Menu section updated.',
            'data' => new MenuSectionResource($menuSection->fresh('dishes')),
        ]);
    }

    /** Soft delete; the section's dishes go with it via the FK cascade. */
    public function destroy(MenuSection $menuSection): JsonResponse
    {
        $this->authorize('delete', $menuSection);

        $menuSection->delete();

        return response()->json(['message' => 'Menu section deleted.']);
    }

    /**
     * Bulk reorder from a drag-and-drop list, so the dashboard sends one
     * request instead of one per row.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array', 'max:100'],
            'sections.*.id' => ['required', 'integer', 'exists:menu_sections,id'],
            'sections.*.sort_order' => ['required', 'integer', 'between:0,10000'],
        ]);

        foreach ($validated['sections'] as $row) {
            $section = MenuSection::find($row['id']);

            if ($section && $request->user()->can('update', $section)) {
                $section->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['message' => 'Menu order saved.']);
    }
}
