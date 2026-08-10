<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ModerateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    /** The moderation queue — admins see all, owners see their own venues. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Review::class);

        $user = $request->user();

        // Owners only moderate reviews left on venues they own.
        $scopeToOwner = fn (Builder $q) => $q->when(
            ! $user->isAdmin(),
            fn (Builder $inner) => $inner->whereHas(
                'restaurant',
                fn (Builder $r) => $r->where('owner_id', $user->id)
            )
        );

        $paginator = Review::query()
            ->with(['user', 'restaurant.category', 'reply.user'])
            ->tap($scopeToOwner)
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->query('status')))
            ->when($request->filled('restaurant_id'), fn (Builder $q) => $q->where('restaurant_id', $request->query('restaurant_id')))
            // Pending first: that is what the moderator actually came here for.
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                // Badge count for the sidebar, independent of the current filter.
                'pending_total' => Review::query()
                    ->tap($scopeToOwner)
                    ->where('status', ReviewStatus::Pending)
                    ->count(),
            ],
        ]);
    }

    /**
     * Approving or rejecting rewrites the restaurant's cached rating via the
     * Review model's saved() hook, so the score updates immediately.
     */
    public function updateStatus(ModerateReviewRequest $request, Review $review): JsonResponse
    {
        $status = ReviewStatus::from($request->validated('status'));

        $review->update([
            'status' => $status,
            'published_at' => $status === ReviewStatus::Approved ? ($review->published_at ?? now()) : null,
        ]);

        return response()->json([
            'message' => match ($status) {
                ReviewStatus::Approved => 'Review published.',
                ReviewStatus::Rejected => 'Review rejected.',
                ReviewStatus::Pending => 'Review returned to the queue.',
            },
            'data' => new ReviewResource($review->fresh(['user', 'restaurant', 'reply.user'])),
        ]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('moderate', $review);

        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }
}
