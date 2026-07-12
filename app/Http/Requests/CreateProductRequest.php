<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'business_price' => ['nullable', 'integer', 'min:0'],
            'business_min_quantity' => ['nullable', 'integer', 'min:1'],
            'inventory' => ['required', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ];
    }
}
