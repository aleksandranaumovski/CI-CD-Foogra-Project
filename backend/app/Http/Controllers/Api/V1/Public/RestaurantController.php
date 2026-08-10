<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\RestaurantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\IndexRestaurantRequest;
use App\Http\Resources\MenuSectionResource;
use App\Http\Resources\RestaurantCardResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\RestaurantSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RestaurantController extends Controller
{
    public function __construct(private readonly RestaurantSearch $search) {}

    /**
     * The listing page: search, filters, sorting, pagination and sidebar facets
     * in a single round trip.
     */
    public function index(IndexRestaurantRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 12);

        $query = $this->search->apply(
            Restaurant::query()->published()->with(['category', 'openingHours']),
            $filters
        );

        $paginator = $query->paginate($perPage)->withQueryString();

        $items = collect($paginator->items());

        $this->markWishlisted($items, $request);

        return response()->json([
            'data' => RestaurantCardResource::collection($items),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'facets' => $this->search->facets($filters),
            'applied_filters' => $filters,
        ]);
    }

    /** Powers the home page's "Popular Restaurants" carousel. */
    public function featured(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->published()
            ->with(['category', 'openingHours'])
            ->featured()
            ->orderByDesc('rating_avg')
            ->limit((int) $request->integer('limit', 8))
            ->get();

        $this->markWishlisted($restaurants, $request);

        return response()->json(['data' => RestaurantCardResource::collection($restaurants)]);
    }

    /** Powers "Our Very Best Deals" — biggest discounts first. */
    public function deals(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->published()
            ->with(['category', 'openingHours'])
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->orderByDesc('rating_avg')
            ->limit((int) $request->integer('limit', 6))
            ->get();

        $this->markWishlisted($restaurants, $request);

        return response()->json(['data' => RestaurantCardResource::collection($restaurants)]);
    }

    /** The detail page: everything needed to render it in one request. */
    public function show(Request $request, Restaurant $restaurant): JsonResponse
    {
        /*
         * Draft and archived venues 404 for the public, but stay readable by
         * their owner and by admins so the dashboard can preview them.
         */
        abort_unless(
            $restaurant->status === RestaurantStatus::Published
                || (bool) $request->user()?->can('view', $restaurant),
            404,
            'The requested restaurant could not be found.'
        );

        $restaurant->load([
            'category',
            'owner',
            'images',
            'openingHours',
            'menuSections.dishes' => fn ($q) => $q->available(),
            'reviews' => fn ($q) => $q->approved()
                ->with(['user', 'reply.user'])
                ->orderByDesc('published_at')
                ->limit(10),
        ]);

        $this->markWishlisted(collect([$restaurant]), $request);

        return response()->json([
            'data' => new RestaurantResource($restaurant),
            'rating_breakdown' => $this->ratingBreakdown($restaurant),
        ]);
    }

    public function menu(Restaurant $restaurant): JsonResponse
    {
        $sections = $restaurant->menuSections()
            ->with(['dishes' => fn ($q) => $q->available()])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => MenuSectionResource::collection($sections)]);
    }

    /**
     * Bookable slots for a given date, derived from the restaurant's opening
     * hours in 30-minute steps, with anything already in the past removed.
     */
    public function availability(Request $request, Restaurant $restaurant): JsonResponse
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        $hours = $restaurant->openingHours()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_closed', false)
            ->get();

        $services = [];

        foreach ($hours as $slot) {
            $cursor = $date->copy()->setTimeFromTimeString((string) $slot->opens_at);
            $end = $date->copy()->setTimeFromTimeString((string) $slot->closes_at);

            // A dinner service that closes after midnight runs into the next day.
            if ($end->lessThanOrEqualTo($cursor)) {
                $end->addDay();
            }

            $times = [];

            // Stop one sitting before closing so nobody books the last minute.
            while ($cursor->copy()->addHour()->lessThanOrEqualTo($end)) {
                /*
                 * Only instants that actually fall on the requested date. A time
                 * printed as "00:00" from an 18:00–01:00 sitting belongs to the
                 * next calendar day, and a booking carries a calendar date — so
                 * offering it here would produce a slot that then fails
                 * validation. See OpeningHour::covers().
                 */
                if ($cursor->isFuture() && $cursor->isSameDay($date)) {
                    $times[] = $cursor->format('H:i');
                }

                $cursor->addMinutes(30);
            }

            $services[] = [
                'service' => $slot->service,
                'opens_at' => substr((string) $slot->opens_at, 0, 5),
                'closes_at' => substr((string) $slot->closes_at, 0, 5),
                'times' => $times,
            ];
        }

        return response()->json([
            'date' => $date->toDateString(),
            'is_closed' => $hours->isEmpty(),
            'discount_percent' => $restaurant->discount_percent,
            'services' => $services,
        ]);
    }

    /** Type-ahead for the hero search box. */
    public function suggestions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $restaurants = Restaurant::query()
            ->published()
            ->where(fn ($q) => $q->where('name', 'like', "{$term}%")->orWhere('name', 'like', "% {$term}%"))
            ->orderByDesc('rating_avg')
            ->limit(8)
            ->get(['id', 'name', 'slug', 'address', 'city', 'thumbnail_path', 'rating_avg', 'reviews_count', 'average_price', 'discount_percent', 'is_featured', 'category_id', 'latitude', 'longitude']);

        return response()->json(['data' => RestaurantCardResource::collection($restaurants)]);
    }

    /**
     * Flags which of these restaurants the signed-in diner has saved, in one
     * query rather than one per card.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function markWishlisted(Collection $restaurants, Request $request): void
    {
        $user = $request->user();

        if (! $user || $restaurants->isEmpty()) {
            return;
        }

        $saved = $user->wishlists()
            ->whereIn('restaurant_id', $restaurants->pluck('id'))
            ->pluck('restaurant_id')
            ->flip();

        $restaurants->each(function (Restaurant $restaurant) use ($saved): void {
            $restaurant->is_wishlisted = $saved->has($restaurant->id);
        });
    }

    /**
     * The four progress bars on the detail page's Reviews tab.
     *
     * @return array<string, mixed>
     */
    private function ratingBreakdown(Restaurant $restaurant): array
    {
        $averages = $restaurant->reviews()
            ->approved()
            ->selectRaw('
                COUNT(*) AS total,
                COALESCE(AVG(rating_food), 0)     AS food,
                COALESCE(AVG(rating_service), 0)  AS service,
                COALESCE(AVG(rating_location), 0) AS location,
                COALESCE(AVG(rating_price), 0)    AS price
            ')
            ->first();

        return [
            'total' => (int) $averages->total,
            'overall' => (float) $restaurant->rating_avg,
            'label' => $restaurant->score_label,
            'food' => round((float) $averages->food, 1),
            'service' => round((float) $averages->service, 1),
            'location' => round((float) $averages->location, 1),
            'price' => round((float) $averages->price, 1),
        ];
    }
}
