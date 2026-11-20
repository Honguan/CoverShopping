<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('catalog.active-categories'));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
