<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'number',
        'user_id',
        'address_id',
        'coupon_id',
        'shipping_method_id',
        'sales_channel',
        'coupon_code',
        'shipping_method_name',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'total',
        'payment_status',
        'fulfillment_status',
        'return_status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_fee' => 'integer',
            'total' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
