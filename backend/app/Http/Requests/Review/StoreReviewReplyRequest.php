<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->route('review')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Only the restaurant owner can reply to this review.');
    }
}
