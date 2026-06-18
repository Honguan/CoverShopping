<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,active,suspended'],
            'role' => ['nullable', 'in:customer,seller,admin'],
        ];
    }
}
