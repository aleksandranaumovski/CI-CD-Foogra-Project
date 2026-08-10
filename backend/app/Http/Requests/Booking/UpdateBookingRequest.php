<?php

namespace App\Http\Requests\Booking;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $booking = $this->route('booking');
        $canManageStatus = $this->user()?->can('manageStatus', $booking) ?? false;

        return [
            'guest_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'guest_email' => ['sometimes', 'string', 'email:rfc', 'max:255'],
            'guest_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'booking_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:today'],
            'booking_time' => ['sometimes', 'date_format:H:i'],
            'party_size' => ['sometimes', 'integer', 'between:1,30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],

            /*
             * Diners may only cancel. Moving a booking to confirmed/seated/
             * completed is the venue's call.
             */
            'status' => [
                'sometimes',
                Rule::in($canManageStatus
                    ? BookingStatus::values()
                    : [BookingStatus::Cancelled->value]),
            ],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'You can only cancel this booking. Contact the restaurant to change it further.',
        ];
    }
}
