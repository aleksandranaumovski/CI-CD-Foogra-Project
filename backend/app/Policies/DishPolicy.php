<?php

namespace App\Policies;

use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\User;

class DishPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Dish $dish): bool
    {
        return $user->owns($dish->restaurant);
    }

    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    public function update(User $user, Dish $dish): bool
    {
        return $user->owns($dish->restaurant);
    }

    public function delete(User $user, Dish $dish): bool
    {
        return $user->owns($dish->restaurant);
    }
}
