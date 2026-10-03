<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:160'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(back()->withErrors($validator)->onlyInput('email'));
    }
}
