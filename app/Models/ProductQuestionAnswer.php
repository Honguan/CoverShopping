<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductQuestionAnswer extends Model
{
    protected $fillable = [
        'product_question_id',
        'user_id',
        'answer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
