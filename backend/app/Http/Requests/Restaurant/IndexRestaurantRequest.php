<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates every query parameter the listing page can send, so a malformed
 * filter returns a clean 422 instead of a confusing empty result set.
 */
class IndexRestaurantRequest extends FormRequest
{
    public const SORTS = ['popularity', 'rating', 'date', 'price', 'price-desc', 'name', 'distance'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],

            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'exists:categories,slug'],

            'min_rating' => ['nullable', 'numeric', 'between:0,10'],
            'max_rating' => ['nullable', 'numeric', 'between:0,10', 'gte:min_rating'],

            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],

            // Radius search needs all three or none.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius' => ['nullable', 'numeric', 'between:1,500', 'required_with:lat'],

            'open_now' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'has_discount' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'between:1,60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort.in' => 'Sort must be one of: '.implode(', ', self::SORTS).'.',
            'radius.required_with' => 'A radius is required when searching around a coordinate.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept `categories=pizza,sushi` as well as `categories[]=pizza&categories[]=sushi`.
        if (is_string($this->input('categories'))) {
            $this->merge([
                'categories' => array_values(array_filter(
                    array_map('trim', explode(',', $this->input('categories')))
                )),
            ]);
        }
    }
}
