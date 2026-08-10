<?php

namespace App\Http\Requests\Booking;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Guests may book without an account, so this request does not require auth —
 * it validates that the slot is real, in the future, and inside opening hours.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'guest_name' => ['required', 'string', 'min:2', 'max:120'],
            'guest_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'booking_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'between:1,30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $restaurant = Restaurant::with('openingHours')->find($this->input('restaurant_id'));

            if (! $restaurant) {
                return;
            }

            if ($restaurant->status !== RestaurantStatus::Published) {
                $validator->errors()->add('restaurant_id', 'This restaurant is not currently accepting bookings.');

                return;
            }

            $slot = Carbon::parse($this->input('booking_date').' '.$this->input('booking_time'));

            if ($slot->isPast()) {
                $validator->errors()->add('booking_time', 'Please choose a time in the future.');

                return;
            }

            // No day pre-filter: covers() attributes a past-midnight sitting to
            // the previous day's row, which is what makes a 00:30 booking valid.
            $servesThen = $restaurant->openingHours
                ->contains(fn ($hours) => $hours->covers($slot));

            if (! $servesThen) {
                $validator->errors()->add(
                    'booking_time',
                    'The restaurant is closed at that time. Please pick another slot.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Prefill contact details from the signed-in account when omitted.
        if ($user = $this->user()) {
            $this->mergeIfMissing([
                'guest_name' => $user->name,
                'guest_email' => $user->email,
                'guest_phone' => $user->phone,
            ]);
        }
    }
}
