<?php

namespace App\Policies;

use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;

class MenuSectionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, MenuSection $section): bool
    {
        return $user->owns($section->restaurant);
    }

    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->owns($restaurant);
    }

    public function update(User $user, MenuSection $section): bool
    {
        return $user->owns($section->restaurant);
    }

    public function delete(User $user, MenuSection $section): bool
    {
        return $user->owns($section->restaurant);
    }
}
