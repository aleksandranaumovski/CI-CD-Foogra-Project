<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RestaurantImage */
class RestaurantImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'caption' => $this->caption,
            'sort_order' => $this->sort_order,
        ];
    }
}
