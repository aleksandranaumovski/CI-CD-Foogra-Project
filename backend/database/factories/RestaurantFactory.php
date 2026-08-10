<?php

namespace Database\Factories;

use App\Enums\RestaurantStatus;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $image = 'img/location_'.fake()->numberBetween(1, 12).'.jpg';

        return [
            'owner_id' => null,
            'category_id' => Category::factory(),
            'name' => fake()->unique()->company(),
            'tagline' => fake()->catchPhrase(),
            'description' => fake()->paragraphs(3, true),
            'address' => fake()->buildingNumber().' '.fake()->streetName(),
            'city' => fake()->randomElement(['London', 'Manchester', 'Bristol', 'Leeds', 'Brighton']),
            'postal_code' => fake()->bothify('??# #??'),
            'country' => 'GB',
            // Roughly greater London, so the radius filter has something to chew on.
            'latitude' => fake()->randomFloat(7, 51.40, 51.60),
            'longitude' => fake()->randomFloat(7, -0.25, 0.05),
            'phone' => fake()->numerify('+44 20 #### ####'),
            'email' => fake()->unique()->companyEmail(),
            'website' => fake()->url(),
            'average_price' => fake()->numberBetween(12, 90),
            'discount_percent' => fake()->optional(0.6)->randomElement([15, 20, 25, 30, 40, 45, 50]),
            'hero_image_path' => 'img/restaurant_detail_hero.jpg',
            'thumbnail_path' => $image,
            'services' => fake()->randomElements(
                ['Wifi', 'Parking', 'Wheelchair Accessible', 'Outdoor Seating', 'Pet Friendly', 'Takeaway'],
                fake()->numberBetween(2, 4)
            ),
            'payment_methods' => fake()->randomElements(
                ['Visa', 'Mastercard', 'Amex', 'Cash', 'Apple Pay'],
                fake()->numberBetween(2, 4)
            ),
            'social_links' => [
                'facebook' => 'https://facebook.com/'.fake()->userName(),
                'instagram' => 'https://instagram.com/'.fake()->userName(),
                'twitter' => 'https://x.com/'.fake()->userName(),
            ],
            'is_featured' => fake()->boolean(25),
            'status' => RestaurantStatus::Published,
            'published_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RestaurantStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RestaurantStatus::Archived]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }
}
