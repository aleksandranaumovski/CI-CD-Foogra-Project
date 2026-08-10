<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dashboard CRUD for restaurants, plus the ownership rules around it. */
class RestaurantCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Test Kitchen',
            'category_id' => Category::factory()->create()->id,
            'address' => '1 Test Street',
            'city' => 'London',
            'average_price' => 32.5,
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------ create

    public function test_an_owner_can_create_a_restaurant(): void
    {
        $owner = $this->actingAsUser($this->owner());

        $this->postJson("{$this->api}/admin/restaurants", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Kitchen')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('restaurants', [
            'name' => 'Test Kitchen',
            'owner_id' => $owner->id,
            'status' => 'draft',
        ]);
    }

    public function test_a_new_restaurant_gets_a_unique_slug(): void
    {
        $this->actingAsUser($this->owner());

        $this->postJson("{$this->api}/admin/restaurants", $this->payload())->assertCreated();
        $second = $this->postJson("{$this->api}/admin/restaurants", $this->payload())->assertCreated();

        $this->assertSame('test-kitchen-2', $second->json('data.slug'));
    }

    public function test_a_customer_cannot_create_a_restaurant(): void
    {
        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/admin/restaurants", $this->payload())->assertForbidden();
    }

    public function test_creating_a_restaurant_requires_authentication(): void
    {
        $this->postJson("{$this->api}/admin/restaurants", $this->payload())->assertUnauthorized();
    }

    public function test_creating_a_restaurant_validates_its_input(): void
    {
        $this->actingAsUser($this->owner());

        $this->postJson("{$this->api}/admin/restaurants", [
            'name' => 'A',
            'category_id' => 99999,
            'average_price' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id', 'address', 'average_price']);
    }

    public function test_only_an_admin_can_feature_a_restaurant(): void
    {
        $this->actingAsUser($this->owner());
        $this->postJson("{$this->api}/admin/restaurants", $this->payload(['is_featured' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_featured');

        $this->actingAsUser($this->admin());
        $this->postJson("{$this->api}/admin/restaurants", $this->payload(['is_featured' => true]))
            ->assertCreated()
            ->assertJsonPath('data.is_featured', true);
    }

    public function test_only_an_admin_can_assign_another_owner(): void
    {
        $other = $this->owner();
        $this->actingAsUser($this->owner());

        $this->postJson("{$this->api}/admin/restaurants", $this->payload(['owner_id' => $other->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('owner_id');
    }

    // ------------------------------------------------------------------- read

    public function test_an_owner_sees_only_their_own_restaurants(): void
    {
        $owner = $this->owner();
        $category = Category::factory()->create();

        Restaurant::factory()->count(2)->create(['owner_id' => $owner->id, 'category_id' => $category->id]);
        Restaurant::factory()->count(3)->create(['owner_id' => $this->owner()->id, 'category_id' => $category->id]);

        $this->actingAsUser($owner);
        $this->getJson("{$this->api}/admin/restaurants")->assertOk()->assertJsonPath('meta.total', 2);

        $this->actingAsUser($this->admin());
        $this->getJson("{$this->api}/admin/restaurants")->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_an_owner_cannot_read_another_owners_restaurant(): void
    {
        $theirs = Restaurant::factory()->create([
            'owner_id' => $this->owner()->id,
            'category_id' => Category::factory(),
        ]);

        $this->actingAsUser($this->owner());
        $this->getJson("{$this->api}/admin/restaurants/{$theirs->slug}")->assertForbidden();
    }

    // ----------------------------------------------------------------- update

    public function test_an_owner_can_update_their_own_restaurant(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->patchJson("{$this->api}/admin/restaurants/{$restaurant->slug}", [
            'average_price' => 44,
            'status' => 'published',
        ])->assertOk()->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'average_price' => 44.00]);
    }

    public function test_an_owner_cannot_update_another_owners_restaurant(): void
    {
        $theirs = Restaurant::factory()->create([
            'owner_id' => $this->owner()->id,
            'category_id' => Category::factory(),
        ]);

        $this->actingAsUser($this->owner());
        $this->patchJson("{$this->api}/admin/restaurants/{$theirs->slug}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseMissing('restaurants', ['name' => 'Hijacked']);
    }

    public function test_an_admin_can_update_any_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $this->owner()->id,
            'category_id' => Category::factory(),
        ]);

        $this->actingAsUser($this->admin());
        $this->patchJson("{$this->api}/admin/restaurants/{$restaurant->slug}", ['name' => 'Renamed By Admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed By Admin');
    }

    public function test_publishing_stamps_the_published_date(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->draft()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->assertNull($restaurant->published_at);

        $this->patchJson("{$this->api}/admin/restaurants/{$restaurant->slug}", ['status' => 'published'])->assertOk();

        $this->assertNotNull($restaurant->fresh()->published_at);
    }

    // ----------------------------------------------------------------- delete

    public function test_an_owner_can_soft_delete_and_restore_their_restaurant(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->deleteJson("{$this->api}/admin/restaurants/{$restaurant->slug}")->assertOk();
        $this->assertSoftDeleted('restaurants', ['id' => $restaurant->id]);

        $this->postJson("{$this->api}/admin/restaurants/{$restaurant->id}/restore")->assertOk();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'deleted_at' => null]);
    }

    public function test_only_an_admin_can_permanently_delete(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->deleteJson("{$this->api}/admin/restaurants/{$restaurant->id}/force")->assertForbidden();

        $this->actingAsUser($this->admin());
        $this->deleteJson("{$this->api}/admin/restaurants/{$restaurant->id}/force")->assertOk();
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    // ------------------------------------------------------- images and hours

    public function test_an_owner_can_upload_gallery_photos(): void
    {
        Storage::fake('public');

        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->postJson("{$this->api}/admin/restaurants/{$restaurant->slug}/images", [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
            'captions' => ['Front of house', 'The kitchen'],
        ])->assertCreated()->assertJsonCount(2, 'data');

        $this->assertSame(2, $restaurant->images()->count());
        $this->assertSame('Front of house', $restaurant->images()->first()->caption);
    }

    public function test_gallery_uploads_reject_a_non_image(): void
    {
        Storage::fake('public');

        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->postJson("{$this->api}/admin/restaurants/{$restaurant->slug}/images", [
            'images' => [UploadedFile::fake()->create('malware.exe', 100)],
        ])->assertStatus(422)->assertJsonValidationErrors('images.0');
    }

    public function test_an_owner_can_replace_the_whole_timetable(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);
        $this->giveOpeningHours($restaurant);

        $this->putJson("{$this->api}/admin/restaurants/{$restaurant->slug}/opening-hours", [
            'hours' => [
                ['day_of_week' => 1, 'service' => 'lunch', 'is_closed' => false, 'opens_at' => '12:00', 'closes_at' => '15:00'],
                ['day_of_week' => 0, 'service' => 'lunch', 'is_closed' => true],
            ],
        ])->assertOk();

        $this->assertSame(2, $restaurant->openingHours()->count());
        $this->assertDatabaseHas('opening_hours', [
            'restaurant_id' => $restaurant->id,
            'day_of_week' => 1,
            'opens_at' => '12:00:00',
        ]);
    }

    public function test_opening_hours_require_times_unless_closed(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = Restaurant::factory()->create([
            'owner_id' => $owner->id,
            'category_id' => Category::factory(),
        ]);

        $this->putJson("{$this->api}/admin/restaurants/{$restaurant->slug}/opening-hours", [
            'hours' => [['day_of_week' => 1, 'service' => 'lunch', 'is_closed' => false]],
        ])->assertStatus(422)->assertJsonValidationErrors(['hours.0.opens_at', 'hours.0.closes_at']);
    }
}
