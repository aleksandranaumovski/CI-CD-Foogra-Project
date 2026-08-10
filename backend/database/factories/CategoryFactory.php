<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Pizza - Italian', 'Japanese - Sushi', 'Burghers', 'Vegetarian',
            'Bakery', 'Chinese', 'Mexican', 'Indian', 'Thai', 'Steakhouse',
            'Seafood', 'French', 'Greek', 'Korean', 'Vietnamese',
        ]);

        return [
            'name' => $name,
            'icon' => fake()->randomElement([
                'icon-food_icon_pizza', 'icon-food_icon_sushi', 'icon-food_icon_burgher',
                'icon-food_icon_vegetarian', 'icon-food_icon_cake_2', 'icon-food_icon_chinese',
                'icon-food_icon_burrito',
            ]),
            'description' => fake()->sentence(10),
            'average_price' => fake()->numberBetween(20, 70),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
