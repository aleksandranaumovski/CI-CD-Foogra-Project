<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Categories, users, the wishlist and the dashboard statistics endpoint. */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ categories

    public function test_an_admin_can_create_a_category(): void
    {
        $this->actingAsUser($this->admin());

        $this->postJson("{$this->api}/admin/categories", [
            'name' => 'Ethiopian',
            'icon' => 'icon-food_icon_restaurant',
            'average_price' => 28,
        ])->assertCreated()->assertJsonPath('data.slug', 'ethiopian');
    }

    public function test_an_owner_cannot_create_a_category(): void
    {
        $this->actingAsUser($this->owner());

        $this->postJson("{$this->api}/admin/categories", ['name' => 'Sneaky'])->assertForbidden();
    }

    public function test_an_owner_can_still_read_the_category_list(): void
    {
        Category::factory()->count(3)->create();

        $this->actingAsUser($this->owner());
        $this->getJson("{$this->api}/admin/categories")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create(['category_id' => $category->id]);

        $this->actingAsUser($this->admin());

        $this->deleteJson("{$this->api}/admin/categories/{$category->slug}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAsUser($this->admin());
        $this->deleteJson("{$this->api}/admin/categories/{$category->slug}")->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_a_deleted_category_name_can_be_reused(): void
    {
        $this->actingAsUser($this->admin());

        $slug = $this->postJson("{$this->api}/admin/categories", ['name' => 'Ramen'])
            ->assertCreated()->json('data.slug');

        $this->deleteJson("{$this->api}/admin/categories/{$slug}")->assertOk();

        // The name is free again; the slug is uniquified against the trashed row.
        $this->postJson("{$this->api}/admin/categories", ['name' => 'Ramen'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ramen-2');
    }

    // ----------------------------------------------------------------- users

    public function test_only_an_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $this->actingAsUser($this->owner());
        $this->getJson("{$this->api}/admin/users")->assertForbidden();

        $this->actingAsUser($this->admin());
        $this->getJson("{$this->api}/admin/users")->assertOk();
    }

    public function test_an_admin_can_create_a_user_with_any_role(): void
    {
        $this->actingAsUser($this->admin());

        $this->postJson("{$this->api}/admin/users", [
            'name' => 'New Owner',
            'email' => 'created@foogra.test',
            'password' => 'secret123',
            'role' => 'owner',
        ])->assertCreated()->assertJsonPath('data.role', 'owner');
    }

    public function test_an_admin_can_change_a_users_role(): void
    {
        $user = $this->customer();

        $this->actingAsUser($this->admin());
        $this->patchJson("{$this->api}/admin/users/{$user->id}", ['role' => 'owner'])
            ->assertOk()
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_an_admin_cannot_delete_their_own_account_here(): void
    {
        $admin = $this->actingAsUser($this->admin());

        $this->deleteJson("{$this->api}/admin/users/{$admin->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_an_owner_with_restaurants_cannot_be_deleted(): void
    {
        $owner = $this->owner();
        Restaurant::factory()->create(['owner_id' => $owner->id, 'category_id' => Category::factory()]);

        $this->actingAsUser($this->admin());
        $this->deleteJson("{$this->api}/admin/users/{$owner->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_a_user_without_restaurants_can_be_deleted(): void
    {
        $user = $this->customer();

        $this->actingAsUser($this->admin());
        $this->deleteJson("{$this->api}/admin/users/{$user->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_a_users_email_is_hidden_from_other_diners(): void
    {
        $author = $this->customer(['email' => 'private@foogra.test']);
        $restaurant = $this->publishedRestaurant();
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'user_id' => $author->id]);

        $this->actingAsUser($this->customer());

        $payload = $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/reviews")->json('data.0.author');

        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayHasKey('name', $payload);
    }

    // -------------------------------------------------------------- wishlist

    public function test_a_diner_can_toggle_a_restaurant_in_their_wishlist(): void
    {
        $user = $this->actingAsUser($this->customer());
        $restaurant = $this->publishedRestaurant();

        $this->postJson("{$this->api}/wishlist/{$restaurant->slug}/toggle")
            ->assertCreated()
            ->assertJsonPath('is_wishlisted', true);

        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'restaurant_id' => $restaurant->id]);

        $this->postJson("{$this->api}/wishlist/{$restaurant->slug}/toggle")
            ->assertOk()
            ->assertJsonPath('is_wishlisted', false);

        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'restaurant_id' => $restaurant->id]);
    }

    public function test_the_wishlist_flag_appears_on_listing_cards_for_the_owner_of_the_list(): void
    {
        $user = $this->actingAsUser($this->customer());
        $restaurant = $this->publishedRestaurant();
        $user->wishlists()->create(['restaurant_id' => $restaurant->id]);

        $this->getJson("{$this->api}/restaurants")
            ->assertOk()
            ->assertJsonPath('data.0.is_wishlisted', true);
    }

    public function test_the_wishlist_requires_authentication(): void
    {
        $this->getJson("{$this->api}/wishlist")->assertUnauthorized();
    }

    // ----------------------------------------------------------------- stats

    public function test_the_dashboard_scopes_its_figures_to_the_owner(): void
    {
        $owner = $this->owner();
        $mine = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $theirs = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);

        Booking::factory()->count(3)->create(['restaurant_id' => $mine->id, 'user_id' => $this->customer()->id]);
        Booking::factory()->count(4)->create(['restaurant_id' => $theirs->id, 'user_id' => $this->customer()->id]);

        $this->actingAsUser($owner);
        $this->getJson("{$this->api}/admin/stats")
            ->assertOk()
            ->assertJsonPath('scope', 'my-restaurants')
            ->assertJsonPath('restaurants.total', 1)
            ->assertJsonPath('bookings.total', 3)
            // Owners do not see platform-wide user counts.
            ->assertJsonPath('users', null);

        $this->actingAsUser($this->admin());
        $this->getJson("{$this->api}/admin/stats")
            ->assertOk()
            ->assertJsonPath('scope', 'platform')
            ->assertJsonPath('restaurants.total', 2)
            ->assertJsonPath('bookings.total', 7)
            ->assertJsonStructure(['users' => ['total', 'admins', 'owners', 'customers']]);
    }

    public function test_the_dashboard_returns_a_fourteen_day_trend(): void
    {
        $this->actingAsUser($this->admin());

        $this->getJson("{$this->api}/admin/stats")
            ->assertOk()
            ->assertJsonCount(14, 'bookings_trend')
            ->assertJsonStructure(['bookings_trend' => [['date', 'bookings']]]);
    }

    public function test_a_customer_cannot_reach_the_dashboard(): void
    {
        $this->actingAsUser($this->customer());
        $this->getJson("{$this->api}/admin/stats")->assertForbidden();
    }

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->getJson("{$this->api}/admin/stats")->assertUnauthorized();
    }

    // ----------------------------------------------------------- error shape

    public function test_every_error_uses_the_same_envelope(): void
    {
        $this->getJson("{$this->api}/restaurants/does-not-exist")
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->getJson("{$this->api}/admin/stats")
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->getJson("{$this->api}/restaurants?sort=nope")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_the_health_endpoint_responds(): void
    {
        $this->getJson("{$this->api}/health")
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'service', 'time']);
    }
}
