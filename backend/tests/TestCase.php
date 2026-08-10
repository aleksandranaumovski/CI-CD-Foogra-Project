<?php

namespace Tests;

use App\Models\Category;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Every API route lives under this prefix. */
    protected string $api = '/api/v1';

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function owner(array $attributes = []): User
    {
        return User::factory()->owner()->create($attributes);
    }

    protected function customer(array $attributes = []): User
    {
        return User::factory()->customer()->create($attributes);
    }

    /** Authenticates the given user for subsequent requests in this test. */
    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A published restaurant open for lunch and dinner every day, so booking
     * and "open now" assertions always have a valid slot to hit.
     */
    protected function publishedRestaurant(array $attributes = []): Restaurant
    {
        $restaurant = Restaurant::factory()->create([
            'category_id' => Category::factory(),
            ...$attributes,
        ]);

        $this->giveOpeningHours($restaurant);

        return $restaurant->fresh();
    }

    protected function giveOpeningHours(Restaurant $restaurant, bool $closedSundays = false): void
    {
        foreach (range(0, 6) as $day) {
            $closed = $closedSundays && $day === 0;

            foreach ([['lunch', '11:00:00', '15:00:00'], ['dinner', '18:00:00', '23:30:00']] as [$service, $open, $close]) {
                OpeningHour::create([
                    'restaurant_id' => $restaurant->id,
                    'day_of_week' => $day,
                    'service' => $service,
                    'opens_at' => $closed ? null : $open,
                    'closes_at' => $closed ? null : $close,
                    'is_closed' => $closed,
                ]);
            }
        }
    }
}
