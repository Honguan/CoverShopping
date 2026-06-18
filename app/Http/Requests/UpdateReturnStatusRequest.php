<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:requested,approved,rejected,received,refunded'],
        ];
    }
}
