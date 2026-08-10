<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /** The venue's reservation book. Admins see every restaurant's. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = $request->user();

        $paginator = Booking::query()
            ->with(['restaurant.category', 'user'])
            ->when(
                ! $user->isAdmin(),
                fn (Builder $q) => $q->whereHas('restaurant', fn (Builder $r) => $r->where('owner_id', $user->id))
            )
            ->when($request->filled('restaurant_id'), fn (Builder $q) => $q->where('restaurant_id', $request->query('restaurant_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->query('status')))
            ->when($request->filled('date'), fn (Builder $q) => $q->whereDate('booking_date', $request->query('date')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('booking_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('booking_date', '<=', $request->query('to')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->query('q');
                $q->where(fn (Builder $inner) => $inner
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhere('guest_name', 'like', "%{$term}%")
                    ->orWhere('guest_email', 'like', "%{$term}%"));
            })
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        return response()->json([
            'data' => BookingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return response()->json([
            'data' => new BookingResource($booking->load('restaurant.category', 'user')),
        ]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking->update($request->validated());

        return response()->json([
            'message' => 'Booking updated.',
            'data' => new BookingResource($booking->fresh(['restaurant.category', 'user'])),
        ]);
    }

    /** Shortcut used by the confirm / seat / complete buttons in the dashboard. */
    public function transition(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manageStatus', $booking);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', BookingStatus::values())],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking marked as '.$booking->status->label().'.',
            'data' => new BookingResource($booking->fresh(['restaurant.category', 'user'])),
        ]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->json(['message' => 'Booking deleted.']);
    }
}
