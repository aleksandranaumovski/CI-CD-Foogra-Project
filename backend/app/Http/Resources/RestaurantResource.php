<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full restaurant payload used by the detail page and the admin dashboard.
 * Relations only appear when the controller eager-loaded them, so the listing
 * endpoint stays small while the detail endpoint carries everything at once.
 *
 * @mixin \App\Models\Restaurant
 */
class RestaurantResource extends JsonResource
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
            'tagline' => $this->tagline,
            'description' => $this->description,

            'location' => [
                'address' => $this->address,
                'city' => $this->city,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'directions_url' => $this->latitude && $this->longitude
                    ? "https://www.google.com/maps/dir/?api=1&destination={$this->latitude},{$this->longitude}"
                    : null,
                // Only present when the query used the radius filter.
                'distance_km' => $this->when(
                    isset($this->distance_km),
                    fn () => round((float) $this->distance_km, 2)
                ),
            ],

            'contact' => [
                'phone' => $this->phone,
                'email' => $this->email,
                'website' => $this->website,
            ],

            'average_price' => (float) $this->average_price,
            'discount_percent' => $this->discount_percent,

            'rating' => [
                'score' => (float) $this->rating_avg,
                'label' => $this->score_label,
                'reviews_count' => $this->reviews_count,
            ],

            'images' => [
                'hero' => $this->hero_image_url,
                'thumbnail' => $this->thumbnail_url,
                'gallery' => RestaurantImageResource::collection($this->whenLoaded('images')),
            ],

            'services' => $this->services ?? [],
            'payment_methods' => $this->payment_methods ?? [],
            'social_links' => $this->social_links ?? [],

            'is_featured' => $this->is_featured,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),

            // Computed on demand; the listing endpoint eager-loads openingHours for this.
            'is_open_now' => $this->when(
                $this->relationLoaded('openingHours'),
                fn () => $this->isOpenAt()
            ),

            'category' => new CategoryResource($this->whenLoaded('category')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'opening_hours' => OpeningHourResource::collection($this->whenLoaded('openingHours')),
            'menu_sections' => MenuSectionResource::collection($this->whenLoaded('menuSections')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),

            'bookings_count' => $this->bookings_count,
            'wishlisted_count' => $this->whenCounted('wishlists'),
            // Set by the controller for the signed-in viewer.
            'is_wishlisted' => $this->when(
                isset($this->is_wishlisted),
                fn () => (bool) $this->is_wishlisted
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
