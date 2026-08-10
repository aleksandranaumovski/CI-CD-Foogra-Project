<?php

namespace App\Http\Requests\Menu;

use App\Models\Dish;
use App\Models\MenuSection;
use Illuminate\Foundation\Http\FormRequest;

class StoreDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = MenuSection::with('restaurant')->find($this->input('menu_section_id'));

        return $section?->restaurant
            && ($this->user()?->can('create', [Dish::class, $section->restaurant]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // restaurant_id is derived from the section, never accepted from the client.
            'menu_section_id' => ['required', 'integer', 'exists:menu_sections,id'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'between:0,100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'allergens' => ['nullable', 'array', 'max:20'],
            'allergens.*' => ['string', 'max:40'],
            'is_vegetarian' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,10000'],
        ];
    }
}
