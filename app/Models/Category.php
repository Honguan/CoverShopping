<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Active categories ordered parent-first, each followed by its active children.
     * Children of an inactive parent are hidden with it.
     *
     * @return Collection<int, Category>
     */
    public static function activeTree(): Collection
    {
        $active = static::where('is_active', true)->orderBy('name')->orderBy('id')->get();

        return new Collection($active->whereNull('parent_id')
            ->flatMap(fn (Category $root) => [$root, ...$active->where('parent_id', $root->id)])
            ->values()
            ->all());
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
