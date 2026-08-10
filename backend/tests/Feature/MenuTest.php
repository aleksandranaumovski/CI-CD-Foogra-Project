<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\MenuSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_build_a_menu(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);

        $section = $this->postJson("{$this->api}/admin/menu-sections", [
            'restaurant_id' => $restaurant->id,
            'name' => 'Starters',
            'sort_order' => 0,
        ])->assertCreated()->json('data.id');

        $this->postJson("{$this->api}/admin/dishes", [
            'menu_section_id' => $section,
            'name' => 'Burrata di Andria',
            'price' => 6.5,
            'description' => 'Creamy burrata with heritage tomatoes.',
            'is_vegetarian' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Burrata di Andria');

        $this->assertDatabaseHas('dishes', [
            'menu_section_id' => $section,
            // Derived from the parent section, never accepted from the client.
            'restaurant_id' => $restaurant->id,
        ]);
    }

    public function test_a_dish_inherits_its_restaurant_from_its_section(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $section = MenuSection::factory()->create(['restaurant_id' => $restaurant->id]);

        // Even if a client tries to point the dish at another restaurant.
        $this->postJson("{$this->api}/admin/dishes", [
            'menu_section_id' => $section->id,
            'restaurant_id' => 99999,
            'name' => 'Test Dish',
            'price' => 10,
        ])->assertCreated();

        $this->assertDatabaseHas('dishes', ['name' => 'Test Dish', 'restaurant_id' => $restaurant->id]);
    }

    public function test_an_owner_cannot_add_a_section_to_someone_elses_restaurant(): void
    {
        $theirs = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);

        $this->actingAsUser($this->owner());
        $this->postJson("{$this->api}/admin/menu-sections", [
            'restaurant_id' => $theirs->id,
            'name' => 'Sneaky Section',
        ])->assertForbidden();
    }

    public function test_a_dish_cannot_be_moved_to_another_restaurants_section(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $mine = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $theirs = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);

        $mySection = MenuSection::factory()->create(['restaurant_id' => $mine->id]);
        $theirSection = MenuSection::factory()->create(['restaurant_id' => $theirs->id]);
        $dish = Dish::factory()->create(['menu_section_id' => $mySection->id]);

        $this->patchJson("{$this->api}/admin/dishes/{$dish->id}", ['menu_section_id' => $theirSection->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('menu_section_id');
    }

    public function test_a_dish_can_be_marked_unavailable(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $section = MenuSection::factory()->create(['restaurant_id' => $restaurant->id]);
        $dish = Dish::factory()->create(['menu_section_id' => $section->id, 'is_available' => true]);

        $this->patchJson("{$this->api}/admin/dishes/{$dish->id}/availability")
            ->assertOk()
            ->assertJsonPath('data.is_available', false);
    }

    public function test_the_public_menu_hides_unavailable_dishes(): void
    {
        $restaurant = $this->publishedRestaurant();
        $section = MenuSection::factory()->create(['restaurant_id' => $restaurant->id]);

        Dish::factory()->count(2)->create(['menu_section_id' => $section->id, 'is_available' => true]);
        Dish::factory()->unavailable()->create(['menu_section_id' => $section->id]);

        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonCount(2, 'data.0.dishes');
    }

    public function test_deleting_a_section_removes_it_from_the_public_menu(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $section = MenuSection::factory()->create(['restaurant_id' => $restaurant->id]);
        Dish::factory()->create(['menu_section_id' => $section->id]);

        $this->deleteJson("{$this->api}/admin/menu-sections/{$section->id}")->assertOk();

        $this->assertSoftDeleted('menu_sections', ['id' => $section->id]);
        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/menu")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_menu_sections_can_be_reordered_in_bulk(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);

        $first = MenuSection::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 0]);
        $second = MenuSection::factory()->create(['restaurant_id' => $restaurant->id, 'sort_order' => 1]);

        $this->putJson("{$this->api}/admin/menu-sections/reorder", [
            'sections' => [
                ['id' => $first->id, 'sort_order' => 5],
                ['id' => $second->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertSame(5, $first->fresh()->sort_order);
        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);
    }

    public function test_a_dish_price_must_be_valid(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $section = MenuSection::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->postJson("{$this->api}/admin/dishes", [
            'menu_section_id' => $section->id,
            'name' => 'Bad Dish',
            'price' => -3,
        ])->assertStatus(422)->assertJsonValidationErrors('price');
    }
}
