<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            // Matches StoreCategoryRequest: soft-deleted rows do not reserve a name.
            'name' => [
                'sometimes', 'string', 'min:2', 'max:120',
                Rule::unique('categories', 'name')->whereNull('deleted_at')->ignore($categoryId),
            ],
            'slug' => [
                'sometimes', 'string', 'max:140',
                Rule::unique('categories', 'slug')->whereNull('deleted_at')->ignore($categoryId),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'average_price' => ['sometimes', 'numeric', 'between:0,100000'],
            'sort_order' => ['sometimes', 'integer', 'between:0,10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
