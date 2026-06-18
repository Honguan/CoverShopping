<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductVariantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:80', 'unique:product_variants,sku'],
            'option_name' => ['required', 'string', 'max:80'],
            'option_value' => ['required', 'string', 'max:120'],
            'price_delta' => ['nullable', 'integer', 'min:-999999', 'max:999999'],
            'inventory' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
