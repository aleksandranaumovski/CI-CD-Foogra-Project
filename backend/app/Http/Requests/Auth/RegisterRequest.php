<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:40'],
            /*
             * Self-service signup may only create a customer or an owner
             * account. Admins are provisioned by another admin.
             */
            'role' => ['nullable', Rule::in([UserRole::Customer->value, UserRole::Owner->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'You can register as a customer or a restaurant owner.',
        ];
    }
}
