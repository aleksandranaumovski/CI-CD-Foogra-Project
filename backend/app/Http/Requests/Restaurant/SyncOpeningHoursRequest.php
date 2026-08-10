<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replaces a restaurant's whole timetable in one call — simpler for the
 * dashboard than fourteen individual row updates.
 */
class SyncOpeningHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'max:14'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.service' => ['required', Rule::in(['lunch', 'dinner'])],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'required_if:hours.*.is_closed,false', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'hours.*.opens_at.required_if' => 'An opening time is required unless the slot is marked closed.',
            'hours.*.closes_at.required_if' => 'A closing time is required unless the slot is marked closed.',
        ];
    }
}
