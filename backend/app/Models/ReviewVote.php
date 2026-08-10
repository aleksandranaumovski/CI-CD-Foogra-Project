<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['review_id', 'user_id', 'is_helpful'])]
class ReviewVote extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_helpful' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep the counters on `reviews` accurate no matter how the vote changed.
        $refresh = fn (self $vote) => $vote->review?->recalculateVotes();

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
