<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id,user_id,' . $this->user()->id],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'purchase_order_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::prohibitedIf(! $this->user()->canUseBusinessPricing()),
            ],
        ];
    }
}
