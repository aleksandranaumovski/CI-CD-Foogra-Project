<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /** The signed-in diner's own bookings. */
    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope', 'upcoming');

        $bookings = $request->user()->bookings()
            ->with(['restaurant.category'])
            ->when($scope === 'upcoming', fn ($q) => $q->upcoming())
            ->when($scope === 'past', fn ($q) => $q->past())
            ->when($scope === 'all', fn ($q) => $q->orderByDesc('booking_date'))
            ->paginate((int) $request->integer('per_page', 10));

        return response()->json([
            'data' => BookingResource::collection($bookings->items()),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($request->validated('restaurant_id'));

        // One live booking per guest per slot; cancelled ones free the slot up.
        $duplicate = Booking::where('restaurant_id', $restaurant->id)
            ->where('guest_email', $request->validated('guest_email'))
            ->whereDate('booking_date', $request->validated('booking_date'))
            ->whereTime('booking_time', $request->validated('booking_time').':00')
            ->where('status', '!=', BookingStatus::Cancelled)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'booking_time' => 'You already have a booking at this restaurant for that slot.',
            ]);
        }

        $booking = Booking::create([
            ...$request->safe()->except('restaurant_id'),
            'restaurant_id' => $restaurant->id,
            'user_id' => $request->user()?->id,
            // The venue's current promotion is locked in at booking time.
            'discount_percent' => $restaurant->discount_percent,
            'status' => BookingStatus::Pending,
        ]);

        Mail::to($booking->guest_email)->send(new BookingReceived($booking->load('restaurant')));

        return response()->json([
            'message' => "Table requested. Your reference is {$booking->reference}.",
            'data' => new BookingResource($booking->load('restaurant.category')),
        ], 201);
    }

    /**
     * Signed-in diners and the venue can fetch by reference. Guests must also
     * supply the email the booking was made with, so a reference alone is not
     * enough to read someone else's details.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        $emailMatches = $request->query('email')
            && hash_equals(strtolower($booking->guest_email), strtolower((string) $request->query('email')));

        abort_unless(
            ($user && $user->can('view', $booking)) || $emailMatches,
            403,
            'Provide the email address this booking was made with to view it.'
        );

        return response()->json([
            'data' => new BookingResource($booking->load('restaurant.category', 'user')),
        ]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking->update($request->validated());

        return response()->json([
            'message' => 'Booking updated.',
            'data' => new BookingResource($booking->fresh(['restaurant.category'])),
        ]);
    }

    /** Cancels rather than deletes, so the venue keeps its record. */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        if ($booking->status === BookingStatus::Cancelled) {
            return response()->json([
                'message' => 'This booking was already cancelled.',
                'data' => new BookingResource($booking),
            ]);
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancellation_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => 'Booking cancelled.',
            'data' => new BookingResource($booking->fresh(['restaurant.category'])),
        ]);
    }
}
