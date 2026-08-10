<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\Category;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function slot(array $overrides = []): array
    {
        return [
            'booking_date' => now()->addDays(3)->toDateString(),
            'booking_time' => '20:00',
            'party_size' => 2,
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------- guest path

    public function test_a_guest_can_book_a_table_without_an_account(): void
    {
        Mail::fake();
        $restaurant = $this->publishedRestaurant(['discount_percent' => 30]);

        $response = $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest Person',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(),
        ])->assertCreated();

        $reference = $response->json('data.reference');

        $this->assertMatchesRegularExpression('/^FG-[A-Z0-9]{6}$/', $reference);
        $this->assertDatabaseHas('bookings', [
            'reference' => $reference,
            'guest_email' => 'guest@foogra.test',
            'status' => 'pending',
            // The venue's live promotion is locked in at booking time.
            'discount_percent' => 30,
            'user_id' => null,
        ]);

        Mail::assertSent(BookingReceived::class);
    }

    public function test_a_booking_cannot_be_made_in_the_past(): void
    {
        $restaurant = $this->publishedRestaurant();

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(['booking_date' => now()->subDay()->toDateString()]),
        ])->assertStatus(422)->assertJsonValidationErrors('booking_date');
    }

    public function test_a_booking_cannot_be_made_outside_opening_hours(): void
    {
        $restaurant = $this->publishedRestaurant();

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(['booking_time' => '04:00']),
        ])->assertStatus(422)->assertJsonValidationErrors('booking_time');
    }

    public function test_a_booking_cannot_be_made_on_a_closed_day(): void
    {
        $restaurant = Restaurant::factory()->create(['category_id' => Category::factory()]);
        $this->giveOpeningHours($restaurant, closedSundays: true);

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(['booking_date' => now()->next('Sunday')->toDateString()]),
        ])->assertStatus(422)->assertJsonValidationErrors('booking_time');
    }

    /**
     * A 00:30 booking belongs to the previous evening's sitting. Validation has
     * to read Friday's 18:00–01:00 row to accept a Saturday 00:30 table, and
     * must not accept one just because Saturday *also* runs late.
     */
    public function test_a_booking_after_midnight_is_validated_against_the_previous_evening(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-08-05 12:00:00')); // Wednesday

        $saturday = '2026-08-08'; // Friday = day 5, Saturday = day 6

        // Serves Friday 18:00–01:00, closed all day Saturday.
        $late = Restaurant::factory()->create(['category_id' => Category::factory()]);
        OpeningHour::create([
            'restaurant_id' => $late->id, 'day_of_week' => 5, 'service' => 'dinner',
            'opens_at' => '18:00:00', 'closes_at' => '01:00:00', 'is_closed' => false,
        ]);
        OpeningHour::create([
            'restaurant_id' => $late->id, 'day_of_week' => 6, 'service' => 'dinner', 'is_closed' => true,
        ]);

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $late->id,
            'guest_name' => 'Night Owl',
            'guest_email' => 'owl@foogra.test',
            ...$this->slot(['booking_date' => $saturday, 'booking_time' => '00:30']),
        ])->assertCreated();

        // Closed Friday, open Saturday evening — so Saturday 00:30 is not served.
        $earlyWeek = Restaurant::factory()->create(['category_id' => Category::factory()]);
        OpeningHour::create([
            'restaurant_id' => $earlyWeek->id, 'day_of_week' => 5, 'service' => 'dinner', 'is_closed' => true,
        ]);
        OpeningHour::create([
            'restaurant_id' => $earlyWeek->id, 'day_of_week' => 6, 'service' => 'dinner',
            'opens_at' => '18:00:00', 'closes_at' => '01:00:00', 'is_closed' => false,
        ]);

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $earlyWeek->id,
            'guest_name' => 'Too Early',
            'guest_email' => 'early@foogra.test',
            ...$this->slot(['booking_date' => $saturday, 'booking_time' => '00:30']),
        ])->assertStatus(422)->assertJsonValidationErrors('booking_time');
    }

    public function test_a_draft_restaurant_cannot_be_booked(): void
    {
        $restaurant = Restaurant::factory()->draft()->create(['category_id' => Category::factory()]);
        $this->giveOpeningHours($restaurant);

        $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(),
        ])->assertStatus(422)->assertJsonValidationErrors('restaurant_id');
    }

    public function test_the_same_guest_cannot_double_book_one_slot(): void
    {
        Mail::fake();
        $restaurant = $this->publishedRestaurant();

        $payload = [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(),
        ];

        $this->postJson("{$this->api}/bookings", $payload)->assertCreated();
        $this->postJson("{$this->api}/bookings", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_time');
    }

    public function test_a_cancelled_booking_frees_the_slot_up_again(): void
    {
        Mail::fake();
        $restaurant = $this->publishedRestaurant();

        $payload = [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(),
        ];

        $reference = $this->postJson("{$this->api}/bookings", $payload)->assertCreated()->json('data.reference');

        Booking::where('reference', $reference)->first()->update(['status' => BookingStatus::Cancelled]);

        // Re-booking must work — a unique index could not express this rule.
        $this->postJson("{$this->api}/bookings", $payload)->assertCreated();
    }

    // ----------------------------------------------------------------- lookup

    public function test_a_guest_needs_the_matching_email_to_view_a_booking(): void
    {
        Mail::fake();
        $restaurant = $this->publishedRestaurant();

        $reference = $this->postJson("{$this->api}/bookings", [
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@foogra.test',
            ...$this->slot(),
        ])->json('data.reference');

        $this->getJson("{$this->api}/bookings/{$reference}")->assertForbidden();
        $this->getJson("{$this->api}/bookings/{$reference}?email=wrong@foogra.test")->assertForbidden();
        $this->getJson("{$this->api}/bookings/{$reference}?email=guest@foogra.test")
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    // ----------------------------------------------------------- signed in

    public function test_a_signed_in_diner_sees_their_own_bookings(): void
    {
        $user = $this->actingAsUser($this->customer());
        $restaurant = $this->publishedRestaurant();

        Booking::factory()->count(2)->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'booking_date' => now()->addDays(5)->toDateString(),
        ]);
        Booking::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->getJson("{$this->api}/bookings?scope=all")->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_a_diner_can_cancel_their_own_booking(): void
    {
        $user = $this->actingAsUser($this->customer());
        $booking = Booking::factory()->confirmed()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $user->id,
        ]);

        $this->postJson("{$this->api}/bookings/{$booking->reference}/cancel", ['reason' => 'Plans changed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $booking->refresh();
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('Plans changed', $booking->cancellation_reason);
    }

    public function test_a_diner_cannot_cancel_someone_elses_booking(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->customer());
        $this->postJson("{$this->api}/bookings/{$booking->reference}/cancel")->assertForbidden();
    }

    public function test_a_diner_can_only_cancel_not_confirm(): void
    {
        $user = $this->actingAsUser($this->customer());
        $booking = Booking::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $user->id,
        ]);

        $this->patchJson("{$this->api}/bookings/{$booking->reference}", ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->patchJson("{$this->api}/bookings/{$booking->reference}", ['status' => 'cancelled'])->assertOk();
    }

    // ------------------------------------------------------------- the venue

    public function test_the_venue_can_move_a_booking_through_its_lifecycle(): void
    {
        $owner = $this->owner();
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($owner);

        foreach (['confirmed', 'seated', 'completed'] as $status) {
            $this->postJson("{$this->api}/admin/bookings/{$booking->reference}/transition", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertNotNull($booking->fresh()->confirmed_at);
    }

    public function test_an_owner_sees_only_bookings_for_their_own_venues(): void
    {
        $owner = $this->owner();
        $mine = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $theirs = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);

        Booking::factory()->count(3)->create(['restaurant_id' => $mine->id, 'user_id' => $this->customer()->id]);
        Booking::factory()->count(2)->create(['restaurant_id' => $theirs->id, 'user_id' => $this->customer()->id]);

        $this->actingAsUser($owner);
        $this->getJson("{$this->api}/admin/bookings")->assertOk()->assertJsonPath('meta.total', 3);

        $this->actingAsUser($this->admin());
        $this->getJson("{$this->api}/admin/bookings")->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_the_reservation_book_can_be_searched_by_reference(): void
    {
        $owner = $this->owner();
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);
        Booking::factory()->count(3)->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($owner);
        $this->getJson("{$this->api}/admin/bookings?q={$booking->reference}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_customer_cannot_reach_the_reservation_book(): void
    {
        $this->actingAsUser($this->customer());
        $this->getJson("{$this->api}/admin/bookings")->assertForbidden();
    }

    public function test_booking_references_are_unique(): void
    {
        $restaurant = $this->publishedRestaurant();

        $references = Booking::factory()->count(25)->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ])->pluck('reference');

        $this->assertSame($references->count(), $references->unique()->count());
    }
}
