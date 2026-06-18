<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCouponRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'integer', 'min:1'],
            'minimum_subtotal' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
