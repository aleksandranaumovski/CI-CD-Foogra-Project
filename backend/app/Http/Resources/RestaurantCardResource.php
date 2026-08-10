<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The trimmed payload behind a Foogra "strip" card — exactly the fields the
 * home carousels and the listing grid render, and nothing else.
 *
 * @mixin \App\Models\Restaurant
 */
class RestaurantCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'city' => $this->city,
            'thumbnail' => $this->thumbnail_url,
            // The listing map plots these; nullable, since a venue may have no
            // coordinates yet and the radius filter already skips those.
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'average_price' => (float) $this->average_price,
            'discount_percent' => $this->discount_percent,
            'is_featured' => $this->is_featured,
            'rating' => [
                'score' => (float) $this->rating_avg,
                'label' => $this->score_label,
                'reviews_count' => $this->reviews_count,
            ],
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon,
            ]),
            'is_open_now' => $this->when(
                $this->relationLoaded('openingHours'),
                fn () => $this->isOpenAt()
            ),
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 2)
            ),
            'is_wishlisted' => $this->when(
                isset($this->is_wishlisted),
                fn () => (bool) $this->is_wishlisted
            ),
        ];
    }
}
