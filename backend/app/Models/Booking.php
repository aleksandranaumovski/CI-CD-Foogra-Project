<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'restaurant_id', 'user_id', 'guest_name', 'guest_email', 'guest_phone',
    'booking_date', 'booking_time', 'party_size', 'notes', 'discount_percent',
    'status', 'cancellation_reason',
])]
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['booked_for'];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'party_size' => 'integer',
            'discount_percent' => 'integer',
            'status' => BookingStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            $booking->reference ??= static::generateReference();
        });

        static::saving(function (self $booking): void {
            // Stamp the lifecycle transitions exactly once.
            if ($booking->status === BookingStatus::Confirmed && ! $booking->confirmed_at) {
                $booking->confirmed_at = now();
            }

            if ($booking->status === BookingStatus::Cancelled && ! $booking->cancelled_at) {
                $booking->cancelled_at = now();
            }
        });

        $refreshCount = function (self $booking): void {
            $restaurant = $booking->restaurant;

            if ($restaurant) {
                $restaurant->forceFill([
                    'bookings_count' => $restaurant->bookings()->count(),
                ])->saveQuietly();
            }
        };

        static::created($refreshCount);
        static::deleted($refreshCount);
    }

    /** Collision-checked, human-readable code, e.g. "FG-8K3QP2". */
    public static function generateReference(): string
    {
        do {
            $reference = 'FG-'.Str::upper(Str::random(6));
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    // ---------------------------------------------------------------- relations

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---------------------------------------------------------------- behaviour

    /** Date and time recombined into a single instant for the frontend. */
    protected function bookedFor(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->booking_date || ! $this->booking_time) {
                return null;
            }

            return Carbon::parse(
                $this->booking_date->toDateString().' '.$this->booking_time
            )->toIso8601String();
        });
    }

    public function isUpcoming(): bool
    {
        return $this->booking_date?->isFuture() ?? false;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('booking_date', '>=', today())
            ->orderBy('booking_date')
            ->orderBy('booking_time');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('booking_date', '<', today())
            ->orderByDesc('booking_date');
    }
}
