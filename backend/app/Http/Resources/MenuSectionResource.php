<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MenuSection */
class MenuSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_special_offers' => $this->is_special_offers,
            'dishes' => DishResource::collection($this->whenLoaded('dishes')),
            'dishes_count' => $this->whenCounted('dishes'),
        ];
    }
}
