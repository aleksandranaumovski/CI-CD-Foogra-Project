<?php

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Writes reviews whose averages land on the scores printed in the mockups, so
 * the seeded site looks exactly like the design rather than approximately.
 *
 * Performance note: the Review and ReviewVote models recalculate their parent's
 * cached aggregates on every save. That is exactly right for the API, but in a
 * seeder it turns ~1,600 inserts into ~6,000 queries. So the derived values are
 * computed in PHP here, the rows go in as bulk inserts with model events off,
 * and each restaurant's rating is recalculated once at the end.
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', UserRole::Customer)->pluck('id')->all();
        $ownersBySlug = Restaurant::pluck('owner_id', 'slug');

        if ($customers === []) {
            return;
        }

        // slug => the score printed in the mockup
        $targets = collect(RestaurantSeeder::SHOWCASE)
            ->mapWithKeys(fn (array $row) => [str($row[0])->slug()->value() => $row[3]]);

        $restaurants = Restaurant::query()
            ->whereDoesntHave('reviews')
            ->get(['id', 'slug']);

        $voteRows = [];
        $replyRows = [];
        $now = now();

        foreach ($restaurants as $restaurant) {
            $target = $targets[$restaurant->slug] ?? round(mt_rand(62, 96) / 10, 1);
            $reviewers = collect($customers)->shuffle()->take(mt_rand(4, 9));

            foreach ($reviewers->values() as $position => $userId) {
                $ratings = [
                    'rating_food' => $this->jitter($target),
                    'rating_service' => $this->jitter($target),
                    'rating_location' => $this->jitter($target),
                    'rating_price' => $this->jitter($target),
                ];

                // Decide the votes up front so the counters can go in with the row.
                $voters = collect($customers)
                    ->reject(fn (int $id) => $id === $userId)
                    ->shuffle()
                    ->take(mt_rand(0, 6))
                    ->map(fn (int $voterId) => ['user_id' => $voterId, 'is_helpful' => mt_rand(1, 5) > 1]);

                $approved = ! ($position === 0 && mt_rand(1, 4) === 1);
                $publishedAt = $now->copy()->subDays(mt_rand(1, 400));

                // Events are off, so rating_overall is computed here instead of
                // by the model's saving hook.
                $reviewId = Review::withoutEvents(fn () => Review::create([
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $userId,
                    'title' => $this->title($position),
                    'body' => $this->body(),
                    ...$ratings,
                    'rating_overall' => round(array_sum($ratings) / 4, 1),
                    'status' => $approved ? ReviewStatus::Approved : ReviewStatus::Pending,
                    'published_at' => $approved ? $publishedAt : null,
                    'helpful_count' => $voters->where('is_helpful', true)->count(),
                    'unhelpful_count' => $voters->where('is_helpful', false)->count(),
                ])->id);

                foreach ($voters as $vote) {
                    $voteRows[] = [
                        'review_id' => $reviewId,
                        'user_id' => $vote['user_id'],
                        'is_helpful' => $vote['is_helpful'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // The owner answers roughly a third of them.
                if (mt_rand(1, 3) === 1 && ($ownersBySlug[$restaurant->slug] ?? null)) {
                    $replyRows[] = [
                        'review_id' => $reviewId,
                        'user_id' => $ownersBySlug[$restaurant->slug],
                        'body' => 'Thank you for taking the time to write this — we have shared your '
                            .'comments with the whole team and hope to welcome you back soon.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($voteRows, 500) as $chunk) {
            DB::table((new ReviewVote)->getTable())->insert($chunk);
        }

        foreach (array_chunk($replyRows, 500) as $chunk) {
            DB::table((new ReviewReply)->getTable())->insert($chunk);
        }

        // One aggregate pass per restaurant, instead of one per review write.
        Restaurant::query()->chunkById(50, function ($chunk): void {
            foreach ($chunk as $restaurant) {
                $restaurant->recalculateRatings();
            }
        });
    }

    /** Keeps each sub-score within ±0.5 of the target and inside the 1–10 range. */
    private function jitter(float $target): float
    {
        return max(1.0, min(10.0, round(($target * 2) + mt_rand(-1, 1)) / 2));
    }

    private function title(int $position): string
    {
        $titles = [
            'Great Location!!',
            'Awesome Experience',
            'Really great dinner!!',
            'Will definitely come back',
            'Lovely staff, superb food',
            'Good value for money',
            'A little noisy but delicious',
            'Perfect for a quiet weeknight',
            'Everything we hoped for',
        ];

        return $titles[$position % count($titles)];
    }

    private function body(): string
    {
        $openings = [
            'Booked a table for two on a Friday evening and were seated straight away.',
            'We came here for a birthday dinner and the whole evening ran smoothly.',
            'Popped in for lunch on the way past and ended up staying for dessert.',
            'Third time back this year, which probably tells you most of what you need to know.',
        ];

        $middles = [
            'The kitchen clearly cares about its ingredients — everything arrived hot, seasoned properly and looking good.',
            'Portions are generous without being silly, and the specials board is worth reading before you order.',
            'Service was attentive without hovering, and nobody rushed us for the table.',
        ];

        $closings = [
            'Would happily recommend it to anyone in the area.',
            'The only small gripe is that it gets loud once it fills up.',
            'Prices are fair for the quality you get.',
        ];

        return $openings[array_rand($openings)].' '
            .$middles[array_rand($middles)].' '
            .$closings[array_rand($closings)];
    }
}
