<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreRestaurantImageRequest;
use App\Http\Requests\Restaurant\StoreRestaurantRequest;
use App\Http\Requests\Restaurant\SyncOpeningHoursRequest;
use App\Http\Requests\Restaurant\UpdateRestaurantRequest;
use App\Http\Resources\OpeningHourResource;
use App\Http\Resources\RestaurantImageResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use App\Services\ImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestaurantController extends Controller
{
    public function __construct(private readonly ImageStorage $images) {}

    /**
     * Admins see every restaurant; owners see only their own. Same endpoint,
     * scoped by role rather than by a separate route.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Restaurant::class);

        $user = $request->user();

        $paginator = Restaurant::query()
            ->with(['category', 'owner'])
            ->withCount(['reviews', 'bookings', 'wishlists'])
            ->when(! $user->isAdmin(), fn (Builder $q) => $q->where('owner_id', $user->id))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->boolean('trashed'), fn (Builder $q) => $q->onlyTrashed())
            ->orderByDesc('updated_at')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => RestaurantResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        $this->authorize('view', $restaurant);

        $restaurant->load(['category', 'owner', 'images', 'openingHours', 'menuSections.dishes'])
            ->loadCount(['reviews', 'bookings', 'wishlists']);

        return response()->json(['data' => new RestaurantResource($restaurant)]);
    }

    public function store(StoreRestaurantRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['hero_image', 'thumbnail']);

        // Owners always create under their own account; only admins may override.
        $data['owner_id'] = $request->validated('owner_id') ?? $request->user()->id;
        $data['status'] ??= RestaurantStatus::Draft->value;

        if ($request->hasFile('hero_image')) {
            $data['hero_image_path'] = $this->images->store($request->file('hero_image'), 'restaurants');
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $this->images->store($request->file('thumbnail'), 'restaurants');
        }

        $restaurant = Restaurant::create($data);

        return response()->json([
            'message' => 'Restaurant created.',
            'data' => new RestaurantResource($restaurant->load('category', 'owner')),
        ], 201);
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->safe()->except(['hero_image', 'thumbnail']);

        if ($request->hasFile('hero_image')) {
            $data['hero_image_path'] = $this->images->replace(
                $request->file('hero_image'), 'restaurants', $restaurant->hero_image_path
            );
        }

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $this->images->replace(
                $request->file('thumbnail'), 'restaurants', $restaurant->thumbnail_path
            );
        }

        $restaurant->update($data);

        return response()->json([
            'message' => 'Restaurant updated.',
            'data' => new RestaurantResource($restaurant->fresh(['category', 'owner', 'images', 'openingHours'])),
        ]);
    }

    /** Soft delete, so the record (and its bookings) can be recovered. */
    public function destroy(Restaurant $restaurant): JsonResponse
    {
        $this->authorize('delete', $restaurant);

        $restaurant->delete();

        return response()->json(['message' => 'Restaurant moved to trash.']);
    }

    public function restore(int $id): JsonResponse
    {
        $restaurant = Restaurant::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $restaurant);

        $restaurant->restore();

        return response()->json([
            'message' => 'Restaurant restored.',
            'data' => new RestaurantResource($restaurant->load('category', 'owner')),
        ]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $restaurant = Restaurant::withTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $restaurant);

        // Detached files are not covered by the FK cascade, so clean them up.
        $this->images->delete($restaurant->hero_image_path);
        $this->images->delete($restaurant->thumbnail_path);
        $restaurant->images->each(fn (RestaurantImage $image) => $this->images->delete($image->path));

        $restaurant->forceDelete();

        return response()->json(['message' => 'Restaurant permanently deleted.']);
    }

    // ------------------------------------------------------------- gallery

    public function storeImages(StoreRestaurantImageRequest $request, Restaurant $restaurant): JsonResponse
    {
        $captions = $request->input('captions', []);
        $nextOrder = (int) $restaurant->images()->max('sort_order');

        $created = collect($request->file('images'))->map(function ($file, $index) use ($restaurant, $captions, &$nextOrder) {
            return RestaurantImage::create([
                'restaurant_id' => $restaurant->id,
                'path' => $this->images->store($file, "restaurants/{$restaurant->id}/gallery"),
                'caption' => $captions[$index] ?? null,
                'sort_order' => ++$nextOrder,
            ]);
        });

        return response()->json([
            'message' => $created->count().' photo(s) uploaded.',
            'data' => RestaurantImageResource::collection($created),
        ], 201);
    }

    public function destroyImage(RestaurantImage $image): JsonResponse
    {
        $this->authorize('update', $image->restaurant);

        $this->images->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Photo removed.']);
    }

    // -------------------------------------------------------- opening hours

    public function syncOpeningHours(SyncOpeningHoursRequest $request, Restaurant $restaurant): JsonResponse
    {
        DB::transaction(function () use ($request, $restaurant): void {
            $restaurant->openingHours()->delete();

            foreach ($request->validated('hours') as $slot) {
                $restaurant->openingHours()->create([
                    'day_of_week' => $slot['day_of_week'],
                    'service' => $slot['service'],
                    'is_closed' => $slot['is_closed'],
                    'opens_at' => $slot['is_closed'] ? null : $slot['opens_at'],
                    'closes_at' => $slot['is_closed'] ? null : $slot['closes_at'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Opening hours saved.',
            'data' => OpeningHourResource::collection($restaurant->openingHours()->get()),
        ]);
    }
}
