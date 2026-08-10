<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'restaurant_id', 'menu_section_id', 'name', 'description', 'price',
    'image_path', 'allergens', 'is_vegetarian', 'is_available', 'sort_order',
])]
class Dish extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'allergens' => 'array',
            'is_vegetarian' => 'boolean',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * restaurant_id is denormalised for cheap ownership checks — derive it
         * from the parent section so callers can never set the two out of sync.
         */
        static::saving(function (self $dish): void {
            if ($dish->isDirty('menu_section_id') || ! $dish->restaurant_id) {
                $section = MenuSection::find($dish->menu_section_id);

                if ($section) {
                    $dish->restaurant_id = $section->restaurant_id;
                }
            }
        });
    }

    public function menuSection(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Restaurant::resolveUrl($this->image_path));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
