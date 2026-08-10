<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class VoteReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vote', $this->route('review')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // true = "Useful", false = "Not useful".
            'is_helpful' => ['required', 'boolean'],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'You cannot vote on your own review.');
    }
}
