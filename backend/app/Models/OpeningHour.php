<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['restaurant_id', 'day_of_week', 'service', 'opens_at', 'closes_at', 'is_closed'])]
class OpeningHour extends Model
{
    use HasFactory;

    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    protected $appends = ['day_name'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected function dayName(): Attribute
    {
        return Attribute::get(fn (): string => self::DAYS[$this->day_of_week] ?? 'Unknown');
    }

    /**
     * Does this slot cover the given moment?
     *
     * The slot decides for itself whether it is relevant to the moment, so
     * callers must NOT pre-filter the collection by day: a sitting that runs
     * past midnight (18:00–01:00) is still serving at 00:30 the *next* morning,
     * and it is the previous day's row that says so. Pre-filtering to the
     * moment's own weekday would both miss that tail and wrongly credit it to a
     * day whose sitting has not started yet.
     */
    public function covers(Carbon $moment): bool
    {
        if ($this->is_closed || ! $this->opens_at || ! $this->closes_at) {
            return false;
        }

        $now = $moment->format('H:i:s');
        $opens = substr((string) $this->opens_at, 0, 8);
        $closes = substr((string) $this->closes_at, 0, 8);
        $wraps = $closes < $opens;

        // This slot's own weekday: from opening until closing, or until midnight.
        if ($this->day_of_week === $moment->dayOfWeek) {
            return $wraps ? $now >= $opens : ($now >= $opens && $now <= $closes);
        }

        // The day before: only the part of the sitting that ran past midnight.
        if ($wraps && $this->day_of_week === $moment->copy()->subDay()->dayOfWeek) {
            return $now <= $closes;
        }

        return false;
    }
}
