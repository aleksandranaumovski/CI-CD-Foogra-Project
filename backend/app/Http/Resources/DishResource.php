<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Dish */
class DishResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'menu_section_id' => $this->menu_section_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'allergens' => $this->allergens ?? [],
            'is_vegetarian' => $this->is_vegetarian,
            'is_available' => $this->is_available,
            'sort_order' => $this->sort_order,
            'menu_section' => new MenuSectionResource($this->whenLoaded('menuSection')),
        ];
    }
}
