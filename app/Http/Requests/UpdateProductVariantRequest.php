<?php

namespace App\Http\Requests;

class UpdateProductVariantRequest extends CreateProductVariantRequest
{
    public function rules(): array
    {
        // The SKU identifies past order lines, so it stays fixed.
        $rules = parent::rules();
        unset($rules['sku']);
        $rules['expected_inventory'] = ['required', 'integer', 'min:0'];

        return $rules;
    }
}
