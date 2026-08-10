<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewVote>
 */
class ReviewVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'user_id' => User::factory(),
            'is_helpful' => fake()->boolean(75),
        ];
    }
}
