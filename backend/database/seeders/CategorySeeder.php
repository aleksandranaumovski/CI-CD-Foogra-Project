<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The seven categories the Foogra carousel ships with, including the exact
 * icon-font classes used by `css/icons.css`.
 */
class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        ['name' => 'Pizza - Italian', 'icon' => 'icon-food_icon_pizza', 'average_price' => 40],
        ['name' => 'Japanese - Sushi', 'icon' => 'icon-food_icon_sushi', 'average_price' => 50],
        ['name' => 'Burghers', 'icon' => 'icon-food_icon_burgher', 'average_price' => 55],
        ['name' => 'Vegetarian', 'icon' => 'icon-food_icon_vegetarian', 'average_price' => 40],
        ['name' => 'Bakery', 'icon' => 'icon-food_icon_cake_2', 'average_price' => 60],
        ['name' => 'Chinese', 'icon' => 'icon-food_icon_chinese', 'average_price' => 40],
        ['name' => 'Mexican', 'icon' => 'icon-food_icon_burrito', 'average_price' => 35],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            Category::updateOrCreate(
                ['slug' => str($category['name'])->slug()->value()],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'average_price' => $category['average_price'],
                    'description' => "Hand-picked {$category['name']} spots, ranked by real diner reviews.",
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
