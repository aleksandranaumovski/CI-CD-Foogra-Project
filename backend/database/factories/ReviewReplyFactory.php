<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReply>
 */
class ReviewReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'user_id' => User::factory()->owner(),
            'body' => fake()->paragraph(),
        ];
    }
}
