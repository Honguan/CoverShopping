<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewBusinessProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,approved,rejected'],
        ];
    }
}
