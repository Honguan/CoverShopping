<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'option_name',
        'option_value',
        'price_delta',
        'inventory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'inventory' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function displayName(): string
    {
        return $this->option_name . ': ' . $this->option_value;
    }
}
