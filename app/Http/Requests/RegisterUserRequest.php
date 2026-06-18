<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'account' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:users,account'],
            'email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'birthday' => ['nullable', 'date'],
        ];
    }
}
