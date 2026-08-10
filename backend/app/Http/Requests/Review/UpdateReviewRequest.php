<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('review')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:160'],
            'body' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'rating_food' => ['sometimes', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_service' => ['sometimes', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_location' => ['sometimes', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_price' => ['sometimes', 'numeric', 'between:1,10', 'multiple_of:0.5'],
        ];
    }
}
