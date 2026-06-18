<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'user_id',
        'reason',
        'quantity_delta',
        'inventory_after',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'inventory_after' => 'integer',
        ];
    }
}
