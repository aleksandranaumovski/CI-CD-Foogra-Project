<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            || $user->owns($booking->restaurant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** The diner may amend their own booking while it is still open. */
    public function update(User $user, Booking $booking): bool
    {
        if ($user->owns($booking->restaurant)) {
            return true;
        }

        return $booking->user_id === $user->id && $booking->status->isOpen();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->owns($booking->restaurant);
    }

    /** Moving a booking through confirmed → seated → completed is the venue's job. */
    public function manageStatus(User $user, Booking $booking): bool
    {
        return $user->owns($booking->restaurant);
    }
}
