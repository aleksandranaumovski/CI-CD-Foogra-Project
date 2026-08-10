<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantCardResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->whereIn('id', $request->user()->wishlists()->select('restaurant_id'))
            ->with(['category', 'openingHours'])
            ->orderBy('name')
            ->get()
            ->each(fn (Restaurant $restaurant) => $restaurant->is_wishlisted = true);

        return response()->json([
            'data' => RestaurantCardResource::collection($restaurants),
            'meta' => ['total' => $restaurants->count()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
        ]);

        $wishlist = $request->user()->wishlists()->firstOrCreate([
            'restaurant_id' => $validated['restaurant_id'],
        ]);

        return response()->json([
            'message' => $wishlist->wasRecentlyCreated
                ? 'Saved to your wishlist.'
                : 'Already in your wishlist.',
            'restaurant_id' => (int) $validated['restaurant_id'],
            'is_wishlisted' => true,
        ], $wishlist->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Restaurant $restaurant): JsonResponse
    {
        $request->user()->wishlists()
            ->where('restaurant_id', $restaurant->id)
            ->delete();

        return response()->json([
            'message' => 'Removed from your wishlist.',
            'restaurant_id' => $restaurant->id,
            'is_wishlisted' => false,
        ]);
    }

    /** One-tap add/remove for the heart button on a card. */
    public function toggle(Request $request, Restaurant $restaurant): JsonResponse
    {
        $existing = $request->user()->wishlists()
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'Removed from your wishlist.',
                'restaurant_id' => $restaurant->id,
                'is_wishlisted' => false,
            ]);
        }

        $request->user()->wishlists()->create(['restaurant_id' => $restaurant->id]);

        return response()->json([
            'message' => 'Saved to your wishlist.',
            'restaurant_id' => $restaurant->id,
            'is_wishlisted' => true,
        ], 201);
    }
}
