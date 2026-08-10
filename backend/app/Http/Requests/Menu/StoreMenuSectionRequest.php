<?php

namespace App\Http\Requests\Menu;

use App\Models\MenuSection;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = Restaurant::find($this->input('restaurant_id'));

        return $restaurant
            && ($this->user()?->can('create', [MenuSection::class, $restaurant]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'between:0,10000'],
            'is_special_offers' => ['nullable', 'boolean'],
        ];
    }
}
