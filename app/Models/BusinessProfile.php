<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'tax_id',
        'contact_name',
        'contact_phone',
        'billing_email',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
