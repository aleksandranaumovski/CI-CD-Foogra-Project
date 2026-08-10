<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantImage>
 */
class RestaurantImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = fake()->numberBetween(1, 5);

        return [
            'restaurant_id' => Restaurant::factory(),
            'path' => "img/detail_gallery/detail_{$n}.jpg",
            'thumbnail_path' => "img/thumb_detail_{$n}.jpg",
            'caption' => fake()->sentence(4),
            'sort_order' => $n,
        ];
    }
}
