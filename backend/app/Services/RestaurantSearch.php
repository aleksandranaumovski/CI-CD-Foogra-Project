<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns the listing page's validated query parameters into one Eloquent query.
 * Kept out of the controller so the same filter set can be reused by the home
 * page, the listing page and the admin dashboard.
 */
class RestaurantSearch
{
    /** The rating bands in the template's sidebar. */
    private const RATING_BANDS = [
        9 => 'Superb 9+',
        8 => 'Very Good 8+',
        7 => 'Good 7+',
        6 => 'Pleasant 6+',
    ];

    /** The price bands in the template's sidebar. */
    private const PRICE_BANDS = [[0, 50], [50, 100], [100, 150], [150, 200]];

    /**
     * @param  array<string, mixed>  $filters
     * @param  bool  $withDistanceColumn  false for facet counts — the computed
     *                                    distance column cannot appear beside
     *                                    a GROUP BY under only_full_group_by.
     */
    public function apply(Builder $query, array $filters, bool $withDistanceColumn = true): Builder
    {
        $query
            ->search($filters['q'] ?? null)
            ->nearLocation($filters['location'] ?? null);

        if (! empty($filters['categories'])) {
            $slugs = (array) $filters['categories'];
            $query->whereHas('category', fn (Builder $q) => $q->whereIn('categories.slug', $slugs));
        }

        if (isset($filters['min_rating'])) {
            $query->where('restaurants.rating_avg', '>=', (float) $filters['min_rating']);
        }

        if (isset($filters['max_rating'])) {
            $query->where('restaurants.rating_avg', '<=', (float) $filters['max_rating']);
        }

        if (isset($filters['min_price'])) {
            $query->where('restaurants.average_price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('restaurants.average_price', '<=', (float) $filters['max_price']);
        }

        if (! empty($filters['has_discount'])) {
            $query->whereNotNull('restaurants.discount_percent');
        }

        if (! empty($filters['featured'])) {
            $query->featured();
        }

        if (! empty($filters['open_now'])) {
            $query->openAt();
        }

        if (isset($filters['lat'], $filters['lng'], $filters['radius'])) {
            $query->withinRadius(
                (float) $filters['lat'],
                (float) $filters['lng'],
                (float) $filters['radius'],
            );

            if ($withDistanceColumn) {
                $query->withDistance((float) $filters['lat'], (float) $filters['lng']);
            }
        }

        return $query->sorted($filters['sort'] ?? null);
    }

    /**
     * Sidebar facet counts — "Pizza - Italian (12)", "Superb 9+ (06)".
     *
     * Each facet is counted against the other active filters but not against
     * itself, so ticking "Pizza" does not collapse every other category to
     * zero. Two queries total: one grouped by category, one using conditional
     * aggregation for all eight rating and price bands at once.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function facets(array $filters): array
    {
        return [
            'categories' => $this->categoryFacet($filters),
            'ratings' => $this->ratingFacet($filters),
            'prices' => $this->priceFacet($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function categoryFacet(array $filters): array
    {
        return $this->baseQuery($filters, except: ['categories'])
            ->join('categories', 'categories.id', '=', 'restaurants.category_id')
            ->groupBy('categories.slug')
            ->selectRaw('categories.slug AS slug, COUNT(restaurants.id) AS total')
            ->pluck('total', 'slug')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function ratingFacet(array $filters): array
    {
        $selects = [];
        $bindings = [];

        foreach (array_keys(self::RATING_BANDS) as $index => $min) {
            $selects[] = "SUM(CASE WHEN restaurants.rating_avg >= ? THEN 1 ELSE 0 END) AS band_{$index}";
            $bindings[] = $min;
        }

        $row = $this->baseQuery($filters, except: ['min_rating', 'max_rating'])
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        // Walk the bands in the same order the aliases were generated above.
        $out = [];
        $index = 0;

        foreach (self::RATING_BANDS as $min => $label) {
            $out[] = [
                'min_rating' => $min,
                'label' => $label,
                'count' => (int) ($row?->{"band_{$index}"} ?? 0),
            ];
            $index++;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function priceFacet(array $filters): array
    {
        $selects = [];
        $bindings = [];

        foreach (self::PRICE_BANDS as $index => [$min, $max]) {
            $selects[] = "SUM(CASE WHEN restaurants.average_price BETWEEN ? AND ? THEN 1 ELSE 0 END) AS band_{$index}";
            $bindings[] = $min;
            $bindings[] = $max;
        }

        $row = $this->baseQuery($filters, except: ['min_price', 'max_price'])
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        $out = [];

        foreach (self::PRICE_BANDS as $index => [$min, $max]) {
            $out[] = [
                'min_price' => $min,
                'max_price' => $max,
                'label' => "\${$min} — \${$max}",
                'count' => (int) ($row?->{"band_{$index}"} ?? 0),
            ];
        }

        return $out;
    }

    /**
     * A published-restaurants query carrying every active filter except the
     * ones named, with ordering stripped (irrelevant, and illegal beside a
     * GROUP BY on some MySQL configurations).
     *
     * `open_now` is excluded from every facet count as well. It is a filter on
     * the clock rather than on the catalogue, so folding it in would make the
     * sidebar numbers change minute to minute while the user reads them.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $except
     */
    private function baseQuery(array $filters, array $except): Builder
    {
        $except[] = 'sort';
        $except[] = 'open_now';

        return $this->apply(
            Restaurant::query()->published(),
            array_diff_key($filters, array_flip($except)),
            withDistanceColumn: false,
        )->reorder();
    }
}
