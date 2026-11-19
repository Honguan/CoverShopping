<?php

namespace App\Http\Requests;

class UpdateCouponRequest extends CreateCouponRequest
{
    public function rules(): array
    {
        // The code is the customer-facing key on past orders, so it stays fixed.
        $rules = parent::rules();
        unset($rules['code']);

        return $rules;
    }
}
