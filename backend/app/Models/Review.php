<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'restaurant_id', 'user_id', 'title', 'body',
    'rating_food', 'rating_service', 'rating_location', 'rating_price',
    'status', 'published_at',
])]
class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['score_label'];

    protected function casts(): array
    {
        return [
            'rating_food' => 'float',
            'rating_service' => 'float',
            'rating_location' => 'float',
            'rating_price' => 'float',
            'rating_overall' => 'float',
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
            'status' => ReviewStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            // rating_overall is always derived — never trust a client-supplied value.
            $review->rating_overall = round((
                $review->rating_food
                + $review->rating_service
                + $review->rating_location
                + $review->rating_price
            ) / 4, 1);

            if ($review->status === ReviewStatus::Approved && ! $review->published_at) {
                $review->published_at = now();
            }
        });

        // Any write that could change the visible score refreshes the cached aggregates.
        $refresh = fn (self $review) => $review->restaurant?->recalculateRatings();

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);
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

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    // ---------------------------------------------------------------- behaviour

    protected function scoreLabel(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->rating_overall >= 9.0 => 'Superb',
            $this->rating_overall >= 8.0 => 'Very Good',
            $this->rating_overall >= 7.0 => 'Good',
            $this->rating_overall >= 6.0 => 'Pleasant',
            default => 'Fair',
        });
    }

    /** Recounts helpful/unhelpful votes from the votes table. */
    public function recalculateVotes(): void
    {
        $this->forceFill([
            'helpful_count' => $this->votes()->where('is_helpful', true)->count(),
            'unhelpful_count' => $this->votes()->where('is_helpful', false)->count(),
        ])->saveQuietly();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved);
    }
}
