<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'email' => ['sometimes', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', Password::min(8)->letters()->numbers()],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            // Only an admin reaches this request for another user, so role is safe here.
            'role' => [
                'sometimes',
                Rule::in(UserRole::values()),
                Rule::prohibitedIf(fn () => ! $this->user()?->isAdmin()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.prohibited' => 'Only an administrator can change a user\'s role.',
        ];
    }
}
