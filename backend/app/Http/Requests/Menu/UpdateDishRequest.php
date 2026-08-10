<?php

namespace App\Http\Requests\Menu;

use App\Models\MenuSection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('dish')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu_section_id' => [
                'sometimes', 'integer', 'exists:menu_sections,id',
                // A dish can only be moved between sections of the same restaurant.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $target = MenuSection::find($value);
                    $dish = $this->route('dish');

                    if ($target && $dish && $target->restaurant_id !== $dish->restaurant_id) {
                        $fail('A dish can only be moved to a section of the same restaurant.');
                    }
                },
            ],
            'name' => ['sometimes', 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'between:0,100000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'allergens' => ['sometimes', 'nullable', 'array', 'max:20'],
            'allergens.*' => ['string', 'max:40'],
            'is_vegetarian' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,10000'],
        ];
    }
}
