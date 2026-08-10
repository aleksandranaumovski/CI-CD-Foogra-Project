<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\RestaurantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('restaurant')) ?? false;
    }

    /**
     * Every field is `sometimes` so the dashboard can PATCH a single attribute
     * without resending the whole record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $restaurantId = $this->route('restaurant')?->id;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:180'],
            'slug' => ['sometimes', 'string', 'max:200', Rule::unique('restaurants', 'slug')->ignore($restaurantId)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],

            'address' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],

            'average_price' => ['sometimes', 'numeric', 'between:0,100000'],
            'discount_percent' => ['sometimes', 'nullable', 'integer', 'between:1,90'],

            'hero_image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'thumbnail' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            'services' => ['sometimes', 'nullable', 'array', 'max:20'],
            'services.*' => ['string', 'max:60'],
            'payment_methods' => ['sometimes', 'nullable', 'array', 'max:20'],
            'payment_methods.*' => ['string', 'max:60'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.twitter' => ['nullable', 'url', 'max:255'],

            'is_featured' => [
                'sometimes', 'boolean',
                Rule::prohibitedIf(fn () => ! $this->user()?->isAdmin()),
            ],
            'status' => ['sometimes', Rule::in(RestaurantStatus::values())],

            'owner_id' => [
                'sometimes', 'integer', 'exists:users,id',
                Rule::prohibitedIf(fn () => ! $this->user()?->isAdmin()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.prohibited' => 'Only an administrator can reassign a restaurant to another owner.',
            'is_featured.prohibited' => 'Only an administrator can feature a restaurant on the homepage.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }
    }
}
