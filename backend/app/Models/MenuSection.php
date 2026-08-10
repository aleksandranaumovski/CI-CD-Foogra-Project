<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['restaurant_id', 'name', 'description', 'sort_order', 'is_special_offers'])]
class MenuSection extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_special_offers' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class)->orderBy('sort_order');
    }
}
