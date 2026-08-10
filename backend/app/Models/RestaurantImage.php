<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'path', 'thumbnail_path', 'caption', 'sort_order'])]
class RestaurantImage extends Model
{
    use HasFactory;

    protected $appends = ['url', 'thumbnail_url'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => Restaurant::resolveUrl($this->path));
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => Restaurant::resolveUrl($this->thumbnail_path ?: $this->path)
        );
    }
}
