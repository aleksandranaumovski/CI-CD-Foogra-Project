<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewReplyRequest;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Requests\Review\VoteReviewRequest;
use App\Http\Resources\ReviewReplyResource;
use App\Http\Resources\ReviewResource;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReviewController extends Controller
{
    /** Approved reviews for one restaurant, newest first. */
    public function index(Request $request, Restaurant $restaurant): JsonResponse
    {
        $sort = $request->query('sort', 'recent');

        $paginator = $restaurant->reviews()
            ->approved()
            ->with(['user', 'reply.user'])
            ->when($sort === 'helpful', fn ($q) => $q->orderByDesc('helpful_count'))
            ->when($sort === 'highest', fn ($q) => $q->orderByDesc('rating_overall'))
            ->when($sort === 'lowest', fn ($q) => $q->orderBy('rating_overall'))
            ->when($sort === 'recent', fn ($q) => $q->orderByDesc('published_at'))
            ->paginate((int) $request->integer('per_page', 10))
            ->withQueryString();

        $this->attachMyVotes(collect($paginator->items()), $request);

        return response()->json([
            'data' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** The reviews the signed-in diner has written. */
    public function mine(Request $request): JsonResponse
    {
        $reviews = $request->user()->reviews()
            ->with(['restaurant.category', 'reply.user'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 10));

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request, Restaurant $restaurant): JsonResponse
    {
        /*
         * A soft-deleted review still occupies the (restaurant_id, user_id)
         * unique index, so clear it out before writing the replacement.
         */
        Review::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->where('user_id', $request->user()->id)
            ->forceDelete();

        $review = $restaurant->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            // New reviews queue for moderation before they affect the score.
            'status' => ReviewStatus::Pending,
        ]);

        return response()->json([
            'message' => 'Thanks for your review. It will appear once it has been checked.',
            'data' => new ReviewResource($review->load('user')),
        ], 201);
    }

    public function show(Request $request, Review $review): JsonResponse
    {
        abort_unless(
            $review->status === ReviewStatus::Approved || $request->user()?->can('view', $review),
            404,
            'The requested review could not be found.'
        );

        $review->load(['user', 'reply.user', 'restaurant.category']);
        $this->attachMyVotes(collect([$review]), $request);

        return response()->json(['data' => new ReviewResource($review)]);
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        // An edited review goes back through moderation.
        $review->update([
            ...$request->validated(),
            'status' => ReviewStatus::Pending,
            'published_at' => null,
        ]);

        return response()->json([
            'message' => 'Review updated. It will reappear once it has been checked.',
            'data' => new ReviewResource($review->fresh(['user', 'reply.user'])),
        ]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }

    // ------------------------------------------------------------------ votes

    public function vote(VoteReviewRequest $request, Review $review): JsonResponse
    {
        ReviewVote::updateOrCreate(
            ['review_id' => $review->id, 'user_id' => $request->user()->id],
            ['is_helpful' => $request->boolean('is_helpful')],
        );

        $review->refresh();

        return response()->json([
            'message' => 'Thanks for the feedback.',
            'votes' => [
                'helpful' => $review->helpful_count,
                'unhelpful' => $review->unhelpful_count,
                'mine' => $request->boolean('is_helpful'),
            ],
        ]);
    }

    public function removeVote(Request $request, Review $review): JsonResponse
    {
        ReviewVote::where('review_id', $review->id)
            ->where('user_id', $request->user()->id)
            ->get()
            ->each->delete();

        $review->refresh();

        return response()->json([
            'message' => 'Vote removed.',
            'votes' => [
                'helpful' => $review->helpful_count,
                'unhelpful' => $review->unhelpful_count,
                'mine' => null,
            ],
        ]);
    }

    // --------------------------------------------------------------- replies

    public function reply(StoreReviewReplyRequest $request, Review $review): JsonResponse
    {
        $reply = ReviewReply::updateOrCreate(
            ['review_id' => $review->id],
            ['user_id' => $request->user()->id, 'body' => $request->validated('body')],
        );

        return response()->json([
            'message' => 'Reply published.',
            'data' => new ReviewReplyResource($reply->load('user')),
        ], $reply->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyReply(Request $request, Review $review): JsonResponse
    {
        $this->authorize('reply', $review);

        $review->reply?->delete();

        return response()->json(['message' => 'Reply removed.']);
    }

    /**
     * Resolves the viewer's own vote for each review in one query.
     *
     * @param  Collection<int, Review>  $reviews
     */
    private function attachMyVotes(Collection $reviews, Request $request): void
    {
        $user = $request->user();

        if (! $user || $reviews->isEmpty()) {
            return;
        }

        $votes = ReviewVote::where('user_id', $user->id)
            ->whereIn('review_id', $reviews->pluck('id'))
            ->pluck('is_helpful', 'review_id');

        $reviews->each(function (Review $review) use ($votes): void {
            // null = not voted yet; the resource distinguishes that from false.
            $review->setAttribute(
                'my_vote',
                isset($votes[$review->id]) ? (bool) $votes[$review->id] : null
            );
        });
    }
}
