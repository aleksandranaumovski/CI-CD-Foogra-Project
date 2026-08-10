<?php

namespace App\Http\Requests\Review;

use App\Models\Review;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Review::class, $this->route('restaurant')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required', 'string', 'min:3', 'max:160',
                // One live review per diner per restaurant.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = Review::where('restaurant_id', $this->route('restaurant')?->id)
                        ->where('user_id', $this->user()?->id)
                        ->exists();

                    if ($exists) {
                        $fail('You have already reviewed this restaurant. Edit your existing review instead.');
                    }
                },
            ],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
            // Scored out of 10 in half-point steps, matching the template's UI.
            'rating_food' => ['required', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_service' => ['required', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_location' => ['required', 'numeric', 'between:1,10', 'multiple_of:0.5'],
            'rating_price' => ['required', 'numeric', 'between:1,10', 'multiple_of:0.5'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rating_food' => 'food quality rating',
            'rating_service' => 'service rating',
            'rating_location' => 'location rating',
            'rating_price' => 'price rating',
        ];
    }

    public function messages(): array
    {
        return [
            'body.min' => 'Please write at least 20 characters so your review is useful to other diners.',
        ];
    }
}
