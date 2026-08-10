<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Review $review): bool
    {
        return $review->user_id === $user->id
            || $user->owns($review->restaurant);
    }

    /** Owners must not review their own restaurant — checked in the FormRequest too. */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return ! $user->owns($restaurant);
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    /** Approving / rejecting is a moderation action. */
    public function moderate(User $user, Review $review): bool
    {
        return $user->owns($review->restaurant);
    }

    /** The restaurant's owner posts the public reply. */
    public function reply(User $user, Review $review): bool
    {
        return $user->owns($review->restaurant);
    }

    /** You cannot upvote your own review. */
    public function vote(User $user, Review $review): bool
    {
        return $review->user_id !== $user->id;
    }
}
