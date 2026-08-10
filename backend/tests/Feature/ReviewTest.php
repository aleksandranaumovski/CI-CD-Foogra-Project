<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\ReviewVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function reviewPayload(array $overrides = []): array
    {
        return [
            'title' => 'Really great dinner',
            'body' => 'We came for a birthday and the whole evening ran smoothly from start to finish.',
            'rating_food' => 9,
            'rating_service' => 8.5,
            'rating_location' => 8,
            'rating_price' => 7.5,
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------ write

    public function test_a_diner_can_write_a_review(): void
    {
        $restaurant = $this->publishedRestaurant();
        $user = $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Really great dinner')
            // Nothing goes live until it has been moderated.
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reviews', [
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_the_overall_score_is_derived_not_trusted(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->actingAsUser($this->customer());

        // (9 + 8.5 + 8 + 7.5) / 4 = 8.25 → rounds to 8.3
        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertCreated()
            ->assertJsonPath('data.ratings.overall', 8.3);
    }

    public function test_writing_a_review_requires_authentication(): void
    {
        $restaurant = $this->publishedRestaurant();

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertUnauthorized();
    }

    public function test_a_diner_cannot_review_the_same_restaurant_twice(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())->assertCreated();
        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_an_owner_cannot_review_their_own_restaurant(): void
    {
        $owner = $this->actingAsUser($this->owner());
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertForbidden();
    }

    public function test_review_scores_must_be_in_range_and_in_half_steps(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload([
            'rating_food' => 11,
            'rating_service' => 8.3,
        ]))->assertStatus(422)->assertJsonValidationErrors(['rating_food', 'rating_service']);
    }

    public function test_a_review_body_must_be_substantial(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload([
            'body' => 'Nice.',
        ]))->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_a_diner_can_re_review_after_deleting_their_previous_one(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->actingAsUser($this->customer());

        $first = $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertCreated()->json('data.id');

        $this->deleteJson("{$this->api}/reviews/{$first}")->assertOk();

        // The soft-deleted row still occupies the unique index, so this would
        // fail at the database level if the controller did not clear it first.
        $this->postJson("{$this->api}/restaurants/{$restaurant->slug}/reviews", $this->reviewPayload())
            ->assertCreated();
    }

    // ---------------------------------------------------------------- editing

    public function test_a_diner_can_edit_their_own_review(): void
    {
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($review->user);

        $this->patchJson("{$this->api}/reviews/{$review->id}", ['title' => 'Updated headline'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated headline')
            // An edit sends it back through moderation.
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_a_diner_cannot_edit_someone_elses_review(): void
    {
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->customer());
        $this->patchJson("{$this->api}/reviews/{$review->id}", ['title' => 'Hijacked'])->assertForbidden();
    }

    // ---------------------------------------------------------------- ratings

    public function test_approving_a_review_updates_the_restaurants_cached_score(): void
    {
        $restaurant = $this->publishedRestaurant();
        $this->assertSame(0.0, $restaurant->rating_avg);

        $review = Review::factory()->pending()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
            'rating_food' => 10, 'rating_service' => 10, 'rating_location' => 10, 'rating_price' => 10,
        ]);

        $this->assertSame(0.0, $restaurant->fresh()->rating_avg, 'A pending review must not count.');

        $this->actingAsUser($this->admin());
        $this->patchJson("{$this->api}/admin/reviews/{$review->id}/status", ['status' => 'approved'])->assertOk();

        $restaurant->refresh();
        $this->assertSame(10.0, $restaurant->rating_avg);
        $this->assertSame(1, $restaurant->reviews_count);
    }

    public function test_deleting_a_review_recalculates_the_score(): void
    {
        $restaurant = $this->publishedRestaurant();

        Review::factory()->create([
            'restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id,
            'rating_food' => 10, 'rating_service' => 10, 'rating_location' => 10, 'rating_price' => 10,
        ]);
        $second = Review::factory()->create([
            'restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id,
            'rating_food' => 6, 'rating_service' => 6, 'rating_location' => 6, 'rating_price' => 6,
        ]);

        $this->assertSame(8.0, $restaurant->fresh()->rating_avg);

        $second->delete();

        $this->assertSame(10.0, $restaurant->fresh()->rating_avg);
        $this->assertSame(1, $restaurant->fresh()->reviews_count);
    }

    public function test_the_public_list_shows_only_approved_reviews(): void
    {
        $restaurant = $this->publishedRestaurant();

        // A fresh reviewer each time — one review per person per restaurant.
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id]);
        Review::factory()->create(['restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id]);
        Review::factory()->pending()->create(['restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id]);
        Review::factory()->rejected()->create(['restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id]);

        $this->getJson("{$this->api}/restaurants/{$restaurant->slug}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    // ------------------------------------------------------------------ votes

    public function test_a_diner_can_mark_a_review_helpful(): void
    {
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/reviews/{$review->id}/vote", ['is_helpful' => true])
            ->assertOk()
            ->assertJsonPath('votes.helpful', 1)
            ->assertJsonPath('votes.unhelpful', 0);
    }

    public function test_voting_again_replaces_the_previous_vote(): void
    {
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->customer());

        $this->postJson("{$this->api}/reviews/{$review->id}/vote", ['is_helpful' => true])->assertOk();
        $this->postJson("{$this->api}/reviews/{$review->id}/vote", ['is_helpful' => false])
            ->assertOk()
            ->assertJsonPath('votes.helpful', 0)
            ->assertJsonPath('votes.unhelpful', 1);

        $this->assertSame(1, ReviewVote::where('review_id', $review->id)->count());
    }

    public function test_a_diner_cannot_vote_on_their_own_review(): void
    {
        $author = $this->customer();
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $author->id,
        ]);

        $this->actingAsUser($author);
        $this->postJson("{$this->api}/reviews/{$review->id}/vote", ['is_helpful' => true])->assertForbidden();
    }

    public function test_a_vote_can_be_withdrawn(): void
    {
        $review = Review::factory()->create([
            'restaurant_id' => $this->publishedRestaurant()->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->customer());
        $this->postJson("{$this->api}/reviews/{$review->id}/vote", ['is_helpful' => true])->assertOk();
        $this->deleteJson("{$this->api}/reviews/{$review->id}/vote")
            ->assertOk()
            ->assertJsonPath('votes.helpful', 0);
    }

    // --------------------------------------------------------------- replying

    public function test_the_owner_can_reply_to_a_review_on_their_restaurant(): void
    {
        $owner = $this->owner();
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $review = Review::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($owner);

        $this->postJson("{$this->api}/reviews/{$review->id}/reply", [
            'body' => 'Thank you for the kind words, we hope to see you again soon.',
        ])->assertCreated();

        $this->assertDatabaseHas('review_replies', ['review_id' => $review->id, 'user_id' => $owner->id]);
    }

    public function test_a_different_owner_cannot_reply(): void
    {
        $restaurant = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);
        $review = Review::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($this->owner());
        $this->postJson("{$this->api}/reviews/{$review->id}/reply", ['body' => 'Not my venue'])
            ->assertForbidden();
    }

    public function test_replying_twice_edits_the_existing_reply(): void
    {
        $owner = $this->owner();
        $restaurant = $this->publishedRestaurant(['owner_id' => $owner->id]);
        $review = Review::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->actingAsUser($owner);
        $this->postJson("{$this->api}/reviews/{$review->id}/reply", ['body' => 'First attempt at a reply.'])
            ->assertCreated();
        $this->postJson("{$this->api}/reviews/{$review->id}/reply", ['body' => 'Corrected reply text here.'])
            ->assertOk();

        $this->assertSame(1, $review->fresh()->reply()->count());
        $this->assertSame('Corrected reply text here.', $review->fresh()->reply->body);
    }

    // ------------------------------------------------------------ moderation

    public function test_an_owner_moderates_only_their_own_venues_reviews(): void
    {
        $mine = $this->publishedRestaurant(['owner_id' => $owner = $this->owner()->id]);
        $theirs = $this->publishedRestaurant(['owner_id' => $this->owner()->id]);

        Review::factory()->pending()->create(['restaurant_id' => $mine->id, 'user_id' => $this->customer()->id]);
        Review::factory()->pending()->create(['restaurant_id' => $theirs->id, 'user_id' => $this->customer()->id]);

        $this->actingAsUser(\App\Models\User::find($owner));
        $this->getJson("{$this->api}/admin/reviews")->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAsUser($this->admin());
        $this->getJson("{$this->api}/admin/reviews")->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_rejecting_a_review_clears_its_published_date(): void
    {
        $restaurant = $this->publishedRestaurant();
        $review = Review::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $this->customer()->id,
        ]);

        $this->assertNotNull($review->published_at);

        $this->actingAsUser($this->admin());
        $this->patchJson("{$this->api}/admin/reviews/{$review->id}/status", ['status' => 'rejected'])->assertOk();

        $review->refresh();
        $this->assertSame(ReviewStatus::Rejected, $review->status);
        $this->assertNull($review->published_at);
        $this->assertSame(0, $restaurant->fresh()->reviews_count);
    }

    public function test_a_customer_cannot_reach_the_moderation_queue(): void
    {
        $this->actingAsUser($this->customer());
        $this->getJson("{$this->api}/admin/reviews")->assertForbidden();
    }

    public function test_the_rating_breakdown_averages_each_criterion(): void
    {
        $restaurant = $this->publishedRestaurant();

        Review::factory()->create([
            'restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id,
            'rating_food' => 10, 'rating_service' => 8, 'rating_location' => 6, 'rating_price' => 4,
        ]);
        Review::factory()->create([
            'restaurant_id' => $restaurant->id, 'user_id' => $this->customer()->id,
            'rating_food' => 8, 'rating_service' => 6, 'rating_location' => 4, 'rating_price' => 2,
        ]);

        // JSON renders a whole float as `9`, not `9.0`, so compare numerically.
        $breakdown = $this->getJson("{$this->api}/restaurants/{$restaurant->slug}")
            ->assertOk()
            ->json('rating_breakdown');

        $this->assertEqualsWithDelta(9.0, $breakdown['food'], 0.01);
        $this->assertEqualsWithDelta(7.0, $breakdown['service'], 0.01);
        $this->assertEqualsWithDelta(5.0, $breakdown['location'], 0.01);
        $this->assertEqualsWithDelta(3.0, $breakdown['price'], 0.01);
        $this->assertSame(2, $breakdown['total']);
    }
}
