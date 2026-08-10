<?php

namespace Database\Factories;

use App\Models\MenuSection;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuSection>
 */
class MenuSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->randomElement(['Starters', 'Main Course', 'Dessert', 'Drinks']),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_special_offers' => false,
        ];
    }

    public function specialOffers(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Special Offers',
            'is_special_offers' => true,
            'sort_order' => 99,
        ]);
    }
}
