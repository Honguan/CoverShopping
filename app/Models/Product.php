<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Attributes\SearchUsingFullText;
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

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('option_name')->orderBy('option_value');
    }

    /** @return HasOne<ProductImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /** @return HasMany<ProductReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', 'published')->latest();
    }

    /** @return HasMany<ProductQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class)->where('status', '!=', 'hidden')->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    #[SearchUsingFullText(['name', 'description'])]
    public function toSearchableArray(): array
    {
        $searchable = [
            'name' => $this->name,
            'description' => $this->description,
        ];

        if (config('scout.driver') === 'database') {
            return $searchable;
        }

        return $searchable + [
            'status' => $this->status,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'price' => $this->price,
            'created_at' => optional($this->created_at)->timestamp,
        ];
    }
}
