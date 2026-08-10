<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('restaurant')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:180'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'You can upload up to 10 photos at a time.',
            'images.*.max' => 'Each photo must be 8 MB or smaller.',
        ];
    }
}
