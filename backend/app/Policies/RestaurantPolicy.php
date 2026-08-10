<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /** Admins bypass every check below. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /** Anyone signed in can browse the dashboard list; it is scoped per-role. */
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    public function restore(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    /** Permanent deletion is an admin-only escape hatch (see before()). */
    public function forceDelete(User $user, Restaurant $restaurant): bool
    {
        return false;
    }

    /** Only admins may hand a restaurant to a different owner. */
    public function reassignOwner(User $user, Restaurant $restaurant): bool
    {
        return false;
    }
}
