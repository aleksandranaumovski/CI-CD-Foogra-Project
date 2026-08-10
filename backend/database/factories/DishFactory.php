<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\MenuSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // restaurant_id is derived from the section by the model's saving hook.
            'menu_section_id' => MenuSection::factory(),
            'name' => fake()->randomElement([
                'Imported Salmon Steak', 'Poke Bowl', 'Ensalada Cesar', 'Oriental',
                'Vegan Burger', 'Indio Fit', 'Truffle Tagliatelle', 'Margherita',
                'Chicken Katsu', 'Tiramisu', 'Panna Cotta', 'Miso Ramen',
            ]).' '.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 4, 32),
            'allergens' => fake()->optional(0.5)->randomElements(
                ['gluten', 'dairy', 'nuts', 'shellfish', 'soy'],
                fake()->numberBetween(1, 3)
            ),
            'is_vegetarian' => fake()->boolean(30),
            'is_available' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => ['is_available' => false]);
    }
}
