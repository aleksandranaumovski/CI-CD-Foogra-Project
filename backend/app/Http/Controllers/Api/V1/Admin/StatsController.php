<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\RestaurantStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The dashboard landing page. Everything is scoped to what the caller is
 * allowed to see: an owner's numbers cover only their own venues.
 */
class StatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $restaurantIds = $isAdmin
            ? null
            : $user->restaurants()->pluck('id');

        $scopeBookings = fn (Builder $q) => $q->when(
            $restaurantIds !== null,
            fn (Builder $inner) => $inner->whereIn('restaurant_id', $restaurantIds)
        );

        $scopeReviews = $scopeBookings;

        return response()->json([
            'scope' => $isAdmin ? 'platform' : 'my-restaurants',
            'restaurants' => [
                'total' => $this->restaurantQuery($user, $isAdmin)->count(),
                'published' => $this->restaurantQuery($user, $isAdmin)->where('status', RestaurantStatus::Published)->count(),
                'draft' => $this->restaurantQuery($user, $isAdmin)->where('status', RestaurantStatus::Draft)->count(),
                'archived' => $this->restaurantQuery($user, $isAdmin)->where('status', RestaurantStatus::Archived)->count(),
            ],
            'bookings' => [
                'total' => Booking::query()->tap($scopeBookings)->count(),
                'pending' => Booking::query()->tap($scopeBookings)->where('status', BookingStatus::Pending)->count(),
                'confirmed' => Booking::query()->tap($scopeBookings)->where('status', BookingStatus::Confirmed)->count(),
                'cancelled' => Booking::query()->tap($scopeBookings)->where('status', BookingStatus::Cancelled)->count(),
                'today' => Booking::query()->tap($scopeBookings)->whereDate('booking_date', today())->count(),
                'upcoming' => Booking::query()->tap($scopeBookings)->whereDate('booking_date', '>=', today())->count(),
                'covers_next_7_days' => (int) Booking::query()
                    ->tap($scopeBookings)
                    ->whereBetween('booking_date', [today(), today()->addDays(7)])
                    ->whereNot('status', BookingStatus::Cancelled)
                    ->sum('party_size'),
            ],
            'reviews' => [
                'total' => Review::query()->tap($scopeReviews)->count(),
                'pending' => Review::query()->tap($scopeReviews)->where('status', ReviewStatus::Pending)->count(),
                'approved' => Review::query()->tap($scopeReviews)->where('status', ReviewStatus::Approved)->count(),
                'average_score' => round(
                    (float) Review::query()->tap($scopeReviews)->where('status', ReviewStatus::Approved)->avg('rating_overall'),
                    1
                ),
            ],
            'users' => $isAdmin ? [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'owners' => User::where('role', 'owner')->count(),
                'customers' => User::where('role', 'customer')->count(),
            ] : null,

            'bookings_trend' => $this->bookingsTrend($scopeBookings),
            'top_restaurants' => $this->topRestaurants($user, $isAdmin),

            'recent_bookings' => BookingResource::collection(
                Booking::query()->tap($scopeBookings)
                    ->with('restaurant.category')
                    ->latest()
                    ->limit(5)
                    ->get()
            ),
            'reviews_awaiting_moderation' => ReviewResource::collection(
                Review::query()->tap($scopeReviews)
                    ->with(['user', 'restaurant'])
                    ->where('status', ReviewStatus::Pending)
                    ->latest()
                    ->limit(5)
                    ->get()
            ),
        ]);
    }

    private function restaurantQuery(User $user, bool $isAdmin): Builder
    {
        return Restaurant::query()->when(! $isAdmin, fn (Builder $q) => $q->where('owner_id', $user->id));
    }

    /** Bookings per day for the last 14 days, zero-filled for missing days. */
    private function bookingsTrend(callable $scope): array
    {
        $start = today()->subDays(13);

        $counts = Booking::query()
            ->tap($scope)
            ->whereDate('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, 13))
            ->map(function (int $offset) use ($start, $counts) {
                $day = $start->copy()->addDays($offset)->toDateString();

                return ['date' => $day, 'bookings' => (int) ($counts[$day] ?? 0)];
            })
            ->all();
    }

    private function topRestaurants(User $user, bool $isAdmin): array
    {
        return $this->restaurantQuery($user, $isAdmin)
            ->where('status', RestaurantStatus::Published)
            ->orderByDesc('bookings_count')
            ->orderByDesc('rating_avg')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'rating_avg', 'reviews_count', 'bookings_count'])
            ->map(fn (Restaurant $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'rating' => (float) $r->rating_avg,
                'reviews' => $r->reviews_count,
                'bookings' => $r->bookings_count,
            ])
            ->all();
    }
}
