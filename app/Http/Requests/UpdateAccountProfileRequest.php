<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'birthday' => ['nullable', 'date'],
        ];
    }
}
