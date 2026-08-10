<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Restaurant::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:restaurants,slug'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],

            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],

            'average_price' => ['required', 'numeric', 'between:0,100000'],
            'discount_percent' => ['nullable', 'integer', 'between:1,90'],

            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],

            'services' => ['nullable', 'array', 'max:20'],
            'services.*' => ['string', 'max:60'],
            'payment_methods' => ['nullable', 'array', 'max:20'],
            'payment_methods.*' => ['string', 'max:60'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.twitter' => ['nullable', 'url', 'max:255'],

            // Homepage promotion is an editorial decision, so admin-only.
            'is_featured' => [
                'nullable', 'boolean',
                Rule::prohibitedIf(fn () => ! $this->user()?->isAdmin()),
            ],
            'status' => ['nullable', Rule::in(RestaurantStatus::values())],

            // Only an admin may create a restaurant on someone else's behalf.
            'owner_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::prohibitedIf(fn () => ! $this->user()?->isAdmin()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.prohibited' => 'Only an administrator can assign a restaurant to another owner.',
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
