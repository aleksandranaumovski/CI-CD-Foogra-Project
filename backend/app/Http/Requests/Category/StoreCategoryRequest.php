<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Uniqueness ignores soft-deleted rows: an admin who removed
             * "Bakery" must be able to create it again. The model's slug
             * generator still checks trashed rows, so the new record gets a
             * distinct slug rather than colliding on the database index.
             */
            'name' => [
                'required', 'string', 'min:2', 'max:120',
                Rule::unique('categories', 'name')->whereNull('deleted_at'),
            ],
            'slug' => [
                'nullable', 'string', 'max:140',
                Rule::unique('categories', 'slug')->whereNull('deleted_at'),
            ],
            // An icon-font class from the Foogra pack, e.g. "icon-food_icon_pizza".
            'icon' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'average_price' => ['nullable', 'numeric', 'between:0,100000'],
            'sort_order' => ['nullable', 'integer', 'between:0,10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
