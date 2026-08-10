<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'restaurant_id' => $this->restaurant_id,
            'guest' => [
                'name' => $this->guest_name,
                'email' => $this->guest_email,
                'phone' => $this->guest_phone,
            ],
            'booking_date' => $this->booking_date?->toDateString(),
            'booking_time' => substr((string) $this->booking_time, 0, 5),
            'booked_for' => $this->booked_for,
            'party_size' => $this->party_size,
            'notes' => $this->notes,
            'discount_percent' => $this->discount_percent,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_upcoming' => $this->isUpcoming(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'restaurant' => new RestaurantCardResource($this->whenLoaded('restaurant')),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
