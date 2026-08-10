<?php

namespace Database\Factories;

use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
class OpeningHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'service' => fake()->randomElement(['lunch', 'dinner']),
            'opens_at' => '11:00:00',
            'closes_at' => '15:00:00',
            'is_closed' => false,
        ];
    }

    public function lunch(int $day): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $day,
            'service' => 'lunch',
            'opens_at' => '11:00:00',
            'closes_at' => '15:00:00',
        ]);
    }

    public function dinner(int $day): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $day,
            'service' => 'dinner',
            'opens_at' => '18:00:00',
            'closes_at' => '01:00:00',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
