<?php

namespace App\Http\Requests;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // An active method must be chosen, otherwise checkout would skip the shipping fee.
            'shipping_method_id' => [
                Rule::requiredIf(fn () => ShippingMethod::where('is_active', true)->exists()),
                'nullable',
                'integer',
                Rule::exists('shipping_methods', 'id')->where('is_active', true),
            ],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id,user_id,'.$this->user()->id],
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
