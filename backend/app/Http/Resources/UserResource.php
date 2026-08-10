<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Forces the private fields on regardless of who is authenticated.
     *
     * Needed by register/login: the account exists but the request is not yet
     * authenticated as it, so the "is this me?" check below would otherwise
     * strip the email out of the very response that created the account.
     */
    private bool $showPrivate = false;

    public function withPrivateFields(bool $show = true): static
    {
        $this->showPrivate = $show;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isSelfOrAdmin = $this->showPrivate
            || ($viewer && ($viewer->id === $this->id || $viewer->isAdmin()));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            // Contact details are never exposed to other diners.
            'email' => $this->when($isSelfOrAdmin, $this->email),
            'phone' => $this->when($isSelfOrAdmin, $this->phone),
            'email_verified_at' => $this->when($isSelfOrAdmin, $this->email_verified_at?->toIso8601String()),
            'restaurants_count' => $this->whenCounted('restaurants'),
            'reviews_count' => $this->whenCounted('reviews'),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
