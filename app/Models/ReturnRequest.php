<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
