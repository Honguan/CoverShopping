<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $fillable = [
        'legacy_id',
        'seller_id',
        'category_id',
        'name',
        'description',
        'price',
        'business_price',
        'business_min_quantity',
        'inventory',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'business_price' => 'integer',
            'business_min_quantity' => 'integer',
            'inventory' => 'integer',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('option_name')->orderBy('option_value');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class)->where('status', 'published')->latest();
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class)->where('status', '!=', 'hidden')->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'price' => $this->price,
            'created_at' => optional($this->created_at)->timestamp,
        ];
    }
}
