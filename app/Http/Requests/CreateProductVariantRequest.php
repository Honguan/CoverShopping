<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductVariantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:80', 'unique:product_variants,sku'],
            'option_name' => ['required', 'string', 'max:80'],
            'option_value' => ['required', 'string', 'max:120'],
            // A discount larger than the cheapest base price would be silently clamped to a free item.
            'price_delta' => ['nullable', 'integer', 'min:'.$this->minimumPriceDelta(), 'max:999999'],
            'inventory' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function minimumPriceDelta(): int
    {
        $product = $this->route('product');

        if (! $product instanceof Product) {
            return -999999;
        }

        return -min($product->price, $product->business_price ?? $product->price);
    }
}
