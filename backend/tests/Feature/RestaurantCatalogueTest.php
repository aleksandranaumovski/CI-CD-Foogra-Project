<?php

namespace Tests\Feature;

use App\Enums\RestaurantStatus;
use App\Models\Category;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The public browsing surface: listing, filtering, sorting, detail. */
class RestaurantCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_returns_only_published_restaurants(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->count(3)->create(['category_id' => $category->id]);
        Restaurant::factory()->draft()->create(['category_id' => $category->id]);
        Restaurant::factory()->archived()->create(['category_id' => $category->id]);

        $this->getJson("{$this->api}/restaurants")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total'], 'facets']);
    }

    public function test_the_listing_paginates(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->count(15)->create(['category_id' => $category->id]);

        $page1 = $this->getJson("{$this->api}/restaurants?per_page=10")->assertOk();
        $page1->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(10, 'data');

        $this->getJson("{$this->api}/restaurants?per_page=10&page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_the_listing_can_be_filtered_by_category(): void
    {
        $pizza = Category::factory()->create(['name' => 'Pizza', 'slug' => 'pizza']);
        $sushi = Category::factory()->create(['name' => 'Sushi', 'slug' => 'sushi']);

        Restaurant::factory()->count(2)->create(['category_id' => $pizza->id]);
        Restaurant::factory()->count(3)->create(['category_id' => $sushi->id]);

        $this->getJson("{$this->api}/restaurants?categories[]=pizza")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson("{$this->api}/restaurants?categories[]=pizza&categories[]=sushi")
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_the_listing_can_be_filtered_by_price_band(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create(['category_id' => $category->id, 'average_price' => 20]);
        Restaurant::factory()->create(['category_id' => $category->id, 'average_price' => 75]);
        Restaurant::factory()->create(['category_id' => $category->id, 'average_price' => 160]);

        $this->getJson("{$this->api}/restaurants?min_price=0&max_price=50")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_listing_can_be_sorted_by_price(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Cheap', 'average_price' => 10]);
        Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Pricey', 'average_price' => 90]);

        $this->getJson("{$this->api}/restaurants?sort=price")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cheap');

        $this->getJson("{$this->api}/restaurants?sort=price-desc")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Pricey');
    }

    public function test_the_listing_can_be_filtered_by_radius(): void
    {
        $category = Category::factory()->create();

        // Central London, and Edinburgh — well outside a 20 km radius.
        Restaurant::factory()->create([
            'category_id' => $category->id, 'name' => 'Near', 'latitude' => 51.5074, 'longitude' => -0.1278,
        ]);
        Restaurant::factory()->create([
            'category_id' => $category->id, 'name' => 'Far', 'latitude' => 55.9533, 'longitude' => -3.1883,
        ]);

        $response = $this->getJson("{$this->api}/restaurants?lat=51.5074&lng=-0.1278&radius=20&sort=distance")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Near');

        $this->assertIsNumeric($response->json('data.0.distance_km'));
    }

    public function test_a_radius_search_requires_all_three_coordinates(): void
    {
        $this->getJson("{$this->api}/restaurants?lat=51.5")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng', 'radius']);
    }

    public function test_an_unknown_sort_is_rejected(): void
    {
        $this->getJson("{$this->api}/restaurants?sort=whatever")
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_full_text_search_matches_a_restaurant_name(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Sushi Temple']);
        Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Best Burghers']);

        $this->getJson("{$this->api}/restaurants?q=Sushi")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Sushi Temple');
    }

    public function test_facet_counts_ignore_the_facet_being_counted(): void
    {
        $pizza = Category::factory()->create(['name' => 'Pizza', 'slug' => 'pizza']);
        $sushi = Category::factory()->create(['name' => 'Sushi', 'slug' => 'sushi']);
        Restaurant::factory()->count(2)->create(['category_id' => $pizza->id]);
        Restaurant::factory()->count(3)->create(['category_id' => $sushi->id]);

        // With Pizza ticked, Sushi must still show its own count rather than 0.
        $facets = $this->getJson("{$this->api}/restaurants?categories[]=pizza")
            ->assertOk()
            ->json('facets.categories');

        $this->assertSame(2, $facets['pizza']);
        $this->assertSame(3, $facets['sushi']);
    }

    public function test_the_detail_page_returns_the_full_payload(): void
    {
        $restaurant = $this->publishedRestaurant(['name' => 'Detail Test']);

        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Detail Test')
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'location' => ['address', 'directions_url'],
                    'contact', 'rating' => ['score', 'label', 'reviews_count'],
                    'images', 'services', 'payment_methods', 'opening_hours', 'menu_sections',
                ],
                'rating_breakdown' => ['total', 'overall', 'food', 'service', 'location', 'price'],
            ]);
    }

    public function test_a_draft_restaurant_is_not_publicly_visible(): void
    {
        $restaurant = Restaurant::factory()->draft()->create(['category_id' => Category::factory()]);

        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}")->assertNotFound();
    }

    public function test_an_unknown_slug_returns_a_json_404(): void
    {
        $this->getJson("{$this->api}/restaurants/nope-not-here")
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_availability_only_offers_slots_inside_opening_hours(): void
    {
        $restaurant = $this->publishedRestaurant();
        $date = now()->addDays(3)->toDateString();

        $response = $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('is_closed', false);

        $lunch = collect($response->json('services'))->firstWhere('service', 'lunch');

        $this->assertContains('11:00', $lunch['times']);
        $this->assertContains('14:00', $lunch['times']);
        // Lunch closes at 15:00 and a sitting needs an hour, so 14:30 is out.
        $this->assertNotContains('14:30', $lunch['times']);
        $this->assertNotContains('16:00', $lunch['times']);
    }

    public function test_availability_reports_a_closed_day(): void
    {
        $restaurant = Restaurant::factory()->create(['category_id' => Category::factory()]);
        $this->giveOpeningHours($restaurant, closedSundays: true);

        $sunday = now()->next('Sunday')->toDateString();

        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/availability?date={$sunday}")
            ->assertOk()
            ->assertJsonPath('is_closed', true)
            ->assertJsonPath('services', []);
    }

    public function test_featured_and_deals_endpoints_return_the_right_subsets(): void
    {
        $category = Category::factory()->create();

        // discount_percent is optional in the factory, so pin it on every row —
        // otherwise the "deals" count depends on a dice roll.
        Restaurant::factory()->create([
            'category_id' => $category->id, 'is_featured' => true, 'discount_percent' => null,
        ]);
        Restaurant::factory()->create([
            'category_id' => $category->id, 'is_featured' => false, 'discount_percent' => 40,
        ]);
        Restaurant::factory()->create([
            'category_id' => $category->id, 'is_featured' => false, 'discount_percent' => null,
        ]);

        $this->getJson("{$this->api}/restaurants/featured")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("{$this->api}/restaurants/deals")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_suggestions_need_at_least_two_characters(): void
    {
        Restaurant::factory()->create(['category_id' => Category::factory(), 'name' => 'Sushi Temple']);

        $this->getJson("{$this->api}/restaurants/suggestions?q=s")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("{$this->api}/restaurants/suggestions?q=su")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_categories_endpoint_counts_only_published_restaurants(): void
    {
        $category = Category::factory()->create(['slug' => 'pizza', 'is_active' => true]);
        Restaurant::factory()->count(2)->create(['category_id' => $category->id]);
        Restaurant::factory()->draft()->create(['category_id' => $category->id]);

        $this->getJson("{$this->api}/categories")
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'pizza')
            ->assertJsonPath('data.0.restaurants_count', 2);
    }

    public function test_an_inactive_category_is_hidden_from_the_public_list(): void
    {
        Category::factory()->create(['slug' => 'visible', 'is_active' => true]);
        Category::factory()->create(['slug' => 'hidden', 'is_active' => false]);

        $slugs = collect($this->getJson("{$this->api}/categories")->json('data'))->pluck('slug');

        $this->assertContains('visible', $slugs);
        $this->assertNotContains('hidden', $slugs);
    }

    public function test_open_now_reflects_the_opening_hours(): void
    {
        $restaurant = Restaurant::factory()->create([
            'category_id' => Category::factory(),
            'status' => RestaurantStatus::Published,
        ]);

        // Open for exactly this minute, every day.
        foreach (range(0, 6) as $day) {
            OpeningHour::create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $day,
                'service' => 'lunch',
                'opens_at' => now()->subHour()->format('H:i:s'),
                'closes_at' => now()->addHour()->format('H:i:s'),
                'is_closed' => false,
            ]);
        }

        $this->assertTrue($restaurant->fresh()->load('openingHours')->isOpenAt());
    }

    public function test_the_listing_can_be_filtered_to_restaurants_open_now(): void
    {
        $category = Category::factory()->create();

        $open = $this->restaurantOpenAllWeek($category, 'Serving Now');
        $shut = Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Closed Today']);

        foreach (range(0, 6) as $day) {
            OpeningHour::create([
                'restaurant_id' => $shut->id,
                'day_of_week' => $day,
                'service' => 'lunch',
                'is_closed' => true,
            ]);
        }

        $this->getJson("{$this->api}/restaurants?open_now=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $open->slug);
    }

    /**
     * The filter has to run in SQL, not over the fetched page: `meta.total` and
     * `last_page` must describe the same set the pages hand back, and no page
     * may carry a closed restaurant.
     */
    public function test_open_now_is_counted_and_paginated_consistently(): void
    {
        $category = Category::factory()->create();

        foreach (range(1, 3) as $n) {
            $this->restaurantOpenAllWeek($category, "Open {$n}");
        }

        // Twice as many closed venues, with no timetable at all.
        Restaurant::factory()->count(6)->create(['category_id' => $category->id]);

        $page1 = $this->getJson("{$this->api}/restaurants?open_now=1&per_page=2")->assertOk();

        $page1->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');

        $page2 = $this->getJson("{$this->api}/restaurants?open_now=1&per_page=2&page=2")->assertOk();

        $page2->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');

        // Every row returned really is open, across both pages.
        $names = collect($page1->json('data'))->concat($page2->json('data'))->pluck('name');

        $this->assertCount(3, $names->unique());
        $names->each(fn (string $name) => $this->assertStringStartsWith('Open', $name));
    }

    /**
     * `open_now` is a filter on the clock, not on the catalogue, so the sidebar
     * counts ignore it — they must not tick down as the evening wears on.
     */
    public function test_open_now_does_not_move_the_sidebar_facet_counts(): void
    {
        $open = Category::factory()->create(['slug' => 'open-cat', 'is_active' => true]);
        $shut = Category::factory()->create(['slug' => 'shut-cat', 'is_active' => true]);

        $this->restaurantOpenAllWeek($open, 'Open Kitchen');
        Restaurant::factory()->create(['category_id' => $shut->id]);

        $facets = $this->getJson("{$this->api}/restaurants?open_now=1")
            ->assertOk()
            ->json('facets.categories');

        // Both categories keep their full count, though only one venue is open.
        $this->assertSame(1, $facets['open-cat'] ?? 0);
        $this->assertSame(1, $facets['shut-cat'] ?? 0);

        // Other filters still narrow the facets as before.
        $narrowed = $this->getJson("{$this->api}/restaurants?open_now=1&min_rating=9")
            ->assertOk()
            ->json('facets.categories');

        $this->assertSame([], $narrowed);
    }

    /**
     * A sitting that runs past midnight belongs to the day it *started*. At
     * 00:30 Saturday it is Friday's 18:00–01:00 row that is still serving —
     * Saturday's own row has not opened yet.
     */
    public function test_a_past_midnight_sitting_is_credited_to_the_day_it_started(): void
    {
        $this->travelTo(Carbon::parse('2026-08-08 00:30:00')); // a Saturday

        $category = Category::factory()->create();

        // Serves Friday 18:00–01:00, then closed all day Saturday.
        $friday = Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Friday Late']);
        OpeningHour::create([
            'restaurant_id' => $friday->id, 'day_of_week' => 5, 'service' => 'dinner',
            'opens_at' => '18:00:00', 'closes_at' => '01:00:00', 'is_closed' => false,
        ]);
        OpeningHour::create([
            'restaurant_id' => $friday->id, 'day_of_week' => 6, 'service' => 'dinner', 'is_closed' => true,
        ]);

        // The mirror image: closed Friday, opens Saturday evening at 18:00.
        $saturday = Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Saturday Only']);
        OpeningHour::create([
            'restaurant_id' => $saturday->id, 'day_of_week' => 5, 'service' => 'dinner', 'is_closed' => true,
        ]);
        OpeningHour::create([
            'restaurant_id' => $saturday->id, 'day_of_week' => 6, 'service' => 'dinner',
            'opens_at' => '18:00:00', 'closes_at' => '01:00:00', 'is_closed' => false,
        ]);

        // Friday's tail is still serving; Saturday's sitting has not begun.
        $this->assertTrue($friday->fresh()->load('openingHours')->isOpenAt());
        $this->assertFalse($saturday->fresh()->load('openingHours')->isOpenAt());

        // The SQL filter agrees with the badge.
        $this->getJson("{$this->api}/restaurants?open_now=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $friday->slug);

        // And by 19:00 that Saturday the roles have swapped.
        $this->travelTo(Carbon::parse('2026-08-08 19:00:00'));

        $this->assertFalse($friday->fresh()->load('openingHours')->isOpenAt());
        $this->assertTrue($saturday->fresh()->load('openingHours')->isOpenAt());

        $this->getJson("{$this->api}/restaurants?open_now=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $saturday->slug);
    }

    public function test_a_sitting_that_closes_after_midnight_still_counts_as_open(): void
    {
        // Half past midnight, mid-service for an 18:00–01:00 dinner sitting.
        $this->travelTo(Carbon::parse('2026-08-08 00:30:00'));

        $category = Category::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'category_id' => $category->id,
            'name' => 'Late Kitchen',
            'status' => RestaurantStatus::Published,
        ]);

        // A venue that shut at 23:00 the same evening, to prove the bound is real.
        $early = Restaurant::factory()->create(['category_id' => $category->id, 'name' => 'Early Kitchen']);

        foreach (range(0, 6) as $day) {
            OpeningHour::create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $day,
                'service' => 'dinner',
                'opens_at' => '18:00:00',
                'closes_at' => '01:00:00',
                'is_closed' => false,
            ]);

            OpeningHour::create([
                'restaurant_id' => $early->id,
                'day_of_week' => $day,
                'service' => 'dinner',
                'opens_at' => '18:00:00',
                'closes_at' => '23:00:00',
                'is_closed' => false,
            ]);
        }

        // The SQL filter and the PHP badge must agree on the wrap-around case.
        $this->assertTrue($restaurant->fresh()->load('openingHours')->isOpenAt());
        $this->assertFalse($early->fresh()->load('openingHours')->isOpenAt());

        $this->getJson("{$this->api}/restaurants?open_now=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $restaurant->slug);
    }

    /**
     * The listing map plots pins from the card payload, so coordinates have to
     * be on it — they were missing, which is why the map had nothing to draw.
     */
    public function test_listing_cards_carry_coordinates_for_the_map(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create([
            'category_id' => $category->id,
            'latitude' => 51.5142,
            'longitude' => -0.0931,
        ]);

        $card = $this->getJson("{$this->api}/restaurants?per_page=1")
            ->assertOk()
            ->json('data.0');

        $this->assertSame(51.5142, $card['latitude']);
        $this->assertSame(-0.0931, $card['longitude']);
    }

    public function test_a_card_without_coordinates_reports_null_rather_than_breaking(): void
    {
        $category = Category::factory()->create();
        Restaurant::factory()->create([
            'category_id' => $category->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $card = $this->getJson("{$this->api}/restaurants?per_page=1")
            ->assertOk()
            ->json('data.0');

        $this->assertNull($card['latitude']);
        $this->assertNull($card['longitude']);
    }

    /**
     * Availability must not offer a time that booking validation would reject.
     * An 18:00–01:00 sitting stops at 23:30 for the requested date; the 00:00
     * slot belongs to the next calendar day.
     */
    public function test_availability_does_not_offer_slots_that_fall_on_the_next_day(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 09:00:00'));

        $category = Category::factory()->create();
        $restaurant = Restaurant::factory()->create(['category_id' => $category->id]);

        foreach (range(0, 6) as $day) {
            OpeningHour::create([
                'restaurant_id' => $restaurant->id, 'day_of_week' => $day, 'service' => 'dinner',
                'opens_at' => '18:00:00', 'closes_at' => '01:00:00', 'is_closed' => false,
            ]);
        }

        $times = collect(
            $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/availability?date=2026-08-08")
                ->assertOk()
                ->json('services')
        )->flatMap(fn (array $service) => $service['times']);

        $this->assertContains('23:30', $times, 'the last slot on the requested day should still be offered');
        $this->assertNotContains('00:00', $times, 'a slot belonging to the following day must not be offered');

        // Every offered time really is covered by the timetable on that date.
        $fresh = $restaurant->fresh()->load('openingHours');

        $this->assertTrue(
            $times->every(fn (string $time) => $fresh->isOpenAt(Carbon::parse("2026-08-08 {$time}"))),
            'availability offered a time the timetable does not cover'
        );
    }

    /** A published restaurant serving this exact minute on every day of the week. */
    private function restaurantOpenAllWeek(Category $category, string $name): Restaurant
    {
        $restaurant = Restaurant::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'status' => RestaurantStatus::Published,
        ]);

        foreach (range(0, 6) as $day) {
            OpeningHour::create([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $day,
                'service' => 'lunch',
                'opens_at' => now()->subHour()->format('H:i:s'),
                'closes_at' => now()->addHour()->format('H:i:s'),
                'is_closed' => false,
            ]);
        }

        return $restaurant;
    }
}
