<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->unique()->safeEmail(),
            'guest_phone' => fake()->numerify('+44 7### ######'),
            'booking_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'booking_time' => fake()->randomElement([
                '12:00:00', '12:30:00', '13:00:00', '13:30:00',
                '20:00:00', '20:30:00', '21:00:00', '21:30:00',
            ]),
            'party_size' => fake()->numberBetween(1, 8),
            'notes' => fake()->optional(0.3)->sentence(),
            'discount_percent' => fake()->optional(0.5)->randomElement([20, 30, 40]),
            'status' => BookingStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'cancellation_reason' => fake()->sentence(6),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
            'booking_date' => fake()->dateTimeBetween('-3 months', '-1 day')->format('Y-m-d'),
        ]);
    }

    /** A walk-in style booking with no account attached. */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null]);
    }
}
