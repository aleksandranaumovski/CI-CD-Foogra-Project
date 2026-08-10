<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'title' => fake()->randomElement([
                'Great Location!!', 'Awesome Experience', 'Really great dinner!!',
                'Will definitely come back', 'Lovely staff, superb food',
                'Good value for money', 'A little noisy but delicious',
            ]),
            'body' => fake()->paragraphs(2, true),
            'rating_food' => fake()->randomElement([6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0]),
            'rating_service' => fake()->randomElement([6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0]),
            'rating_location' => fake()->randomElement([5.0, 6.0, 6.5, 7.0, 8.0, 8.5, 9.0, 9.5]),
            'rating_price' => fake()->randomElement([5.0, 6.0, 6.5, 7.0, 8.0, 8.5, 9.0]),
            'status' => ReviewStatus::Approved,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Rejected,
            'published_at' => null,
        ]);
    }
}
