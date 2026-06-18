<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id,user_id,' . $this->user()->id],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
