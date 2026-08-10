<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Enums\ReviewStatus;
use App\Services\ImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'owner_id', 'category_id', 'name', 'slug', 'tagline', 'description',
    'address', 'city', 'postal_code', 'country', 'latitude', 'longitude',
    'phone', 'email', 'website', 'average_price', 'discount_percent',
    'hero_image_path', 'thumbnail_path', 'services', 'payment_methods',
    'social_links', 'is_featured', 'status', 'published_at',
])]
class Restaurant extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['hero_image_url', 'thumbnail_url', 'score_label'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'average_price' => 'decimal:2',
            'discount_percent' => 'integer',
            'rating_avg' => 'float',
            'reviews_count' => 'integer',
            'bookings_count' => 'integer',
            'services' => 'array',
            'payment_methods' => 'array',
            'social_links' => 'array',
            'is_featured' => 'boolean',
            'status' => RestaurantStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $restaurant): void {
            if (! $restaurant->slug || ($restaurant->isDirty('name') && ! $restaurant->isDirty('slug'))) {
                $restaurant->slug = static::uniqueSlug($restaurant->name, $restaurant->id);
            }

            // Publishing stamps the date once; un-publishing clears it.
            if ($restaurant->status === RestaurantStatus::Published && ! $restaurant->published_at) {
                $restaurant->published_at = now();
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'restaurant';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- relations

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RestaurantImage::class)->orderBy('sort_order');
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', ReviewStatus::Approved);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // ---------------------------------------------------------------- accessors

    protected function heroImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => static::resolveUrl($this->hero_image_path));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => static::resolveUrl($this->thumbnail_path));
    }

    /** The word Foogra prints next to the score: Superb, Very Good, Good… */
    protected function scoreLabel(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->rating_avg >= 9.0 => 'Superb',
            $this->rating_avg >= 8.0 => 'Very Good',
            $this->rating_avg >= 7.0 => 'Good',
            $this->rating_avg >= 6.0 => 'Pleasant',
            $this->rating_avg > 0 => 'Fair',
            default => 'No reviews yet',
        });
    }

    /**
     * Images may be seeded as template-relative paths ("img/location_1.jpg"),
     * uploaded to the public disk, or supplied as absolute URLs.
     *
     * Delegates to ImageStorage::url() so restaurants, dishes, gallery images
     * and user avatars all resolve a path the same way — they drifted apart
     * once already, and avatars ended up pointing at the wrong disk.
     */
    public static function resolveUrl(?string $path): ?string
    {
        return ImageStorage::url($path);
    }

    /**
     * True when the restaurant is serving right now, per its opening hours.
     *
     * Deliberately hands the whole timetable to covers() rather than narrowing
     * to today's rows first — a sitting that runs past midnight belongs to the
     * previous day's row. See OpeningHour::covers().
     */
    public function isOpenAt(?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $this->openingHours->contains(fn (OpeningHour $hours) => $hours->covers($moment));
    }

    // ------------------------------------------------------------------- scopes

    /*
     * Every scope below qualifies its columns with `restaurants.`. The facet
     * queries join `categories`, which shares `name`, `description`,
     * `average_price` and `slug` — unqualified references there are ambiguous
     * and MySQL rejects them outright.
     */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('restaurants.status', RestaurantStatus::Published);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('restaurants.is_featured', true);
    }

    /**
     * Full-text search across the fields a diner would actually type into the
     * hero search box, with a LIKE fallback for short/partial terms that
     * MySQL's natural-language mode would otherwise discard.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->whereFullText(
                ['restaurants.name', 'restaurants.description', 'restaurants.address', 'restaurants.city'],
                $term
            )
                ->orWhere('restaurants.name', 'like', "%{$term}%")
                ->orWhere('restaurants.address', 'like', "%{$term}%")
                ->orWhere('restaurants.city', 'like', "%{$term}%");
        });
    }

    /** Matches the "Address, neighborhood…" box on the home page. */
    public function scopeNearLocation(Builder $query, ?string $location): Builder
    {
        $location = trim((string) $location);

        if ($location === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($location): void {
            $q->where('restaurants.address', 'like', "%{$location}%")
                ->orWhere('restaurants.city', 'like', "%{$location}%")
                ->orWhere('restaurants.postal_code', 'like', "%{$location}%");
        });
    }

    /** The haversine great-circle distance expression, in kilometres. */
    private static function haversineSql(): string
    {
        return '(6371 * acos(
            least(1, greatest(-1,
                cos(radians(?)) * cos(radians(restaurants.latitude)) * cos(radians(restaurants.longitude) - radians(?))
                + sin(radians(?)) * sin(radians(restaurants.latitude))
            ))
        ))';
    }

    /**
     * Filters to restaurants inside the radius slider's range. Filter only —
     * it adds no SELECT, so it stays safe to compose with GROUP BY facet
     * queries under MySQL's only_full_group_by.
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $km): Builder
    {
        return $query
            ->whereNotNull('restaurants.latitude')
            ->whereNotNull('restaurants.longitude')
            ->whereRaw(static::haversineSql().' <= ?', [$lat, $lng, $lat, $km]);
    }

    /**
     * Adds the computed `distance_km` column, so results can be shown and
     * sorted by proximity. Only used for the row list, never for facet counts.
     */
    public function scopeWithDistance(Builder $query, float $lat, float $lng): Builder
    {
        return $query
            ->select('restaurants.*')
            ->selectRaw(static::haversineSql().' AS distance_km', [$lat, $lng, $lat]);
    }

    /**
     * Restaurants serving at the given moment, per their timetable.
     *
     * This mirrors OpeningHour::covers() exactly — including a sitting that
     * closes after midnight — but evaluates in SQL rather than PHP, so it
     * composes with pagination and facet counts. Filtering the fetched page
     * instead would leave `meta.total` counting rows the page no longer holds.
     */
    public function scopeOpenAt(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment ??= now();
        $time = $moment->format('H:i:s');
        $today = $moment->dayOfWeek;
        $yesterday = $moment->copy()->subDay()->dayOfWeek;

        return $query->whereHas('openingHours', function (Builder $slots) use ($time, $today, $yesterday): void {
            $slots
                ->where('opening_hours.is_closed', false)
                ->whereNotNull('opening_hours.opens_at')
                ->whereNotNull('opening_hours.closes_at')
                ->where(function (Builder $covers) use ($time, $today, $yesterday): void {
                    // Today's sitting: from opening until closing, or until midnight.
                    $covers->where(fn (Builder $sameDay) => $sameDay
                        ->where('opening_hours.day_of_week', $today)
                        ->where('opening_hours.opens_at', '<=', $time)
                        ->where(fn (Builder $bound) => $bound
                            ->whereColumn('opening_hours.closes_at', '<', 'opening_hours.opens_at')
                            ->orWhere('opening_hours.closes_at', '>=', $time)));

                    // Yesterday's sitting, only where it ran past midnight.
                    $covers->orWhere(fn (Builder $tail) => $tail
                        ->where('opening_hours.day_of_week', $yesterday)
                        ->whereColumn('opening_hours.closes_at', '<', 'opening_hours.opens_at')
                        ->where('opening_hours.closes_at', '>=', $time));
                });
        });
    }

    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'rating' => $query->orderByDesc('restaurants.rating_avg')->orderByDesc('restaurants.reviews_count'),
            'date' => $query->orderByDesc('restaurants.published_at')->orderByDesc('restaurants.id'),
            'price' => $query->orderBy('restaurants.average_price'),
            'price-desc' => $query->orderByDesc('restaurants.average_price'),
            'name' => $query->orderBy('restaurants.name'),
            'distance' => $query->orderBy('distance_km'),
            // "Popularity" — the template's default: most-reviewed, best-rated first.
            default => $query->orderByDesc('restaurants.is_featured')
                ->orderByDesc('restaurants.reviews_count')
                ->orderByDesc('restaurants.rating_avg'),
        };
    }

    /**
     * Recomputes the cached rating/review aggregates from approved reviews.
     * Called whenever a review is written, moderated, or removed.
     */
    public function recalculateRatings(): void
    {
        $stats = $this->reviews()
            ->where('status', ReviewStatus::Approved)
            ->selectRaw('COUNT(*) AS total, COALESCE(AVG(rating_overall), 0) AS average')
            ->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->total,
            'rating_avg' => round((float) $stats->average, 1),
        ])->saveQuietly();
    }
}
