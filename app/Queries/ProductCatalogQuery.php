<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogQuery
{
    public function paginate(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $keyword = trim((string) $request->string('q'));
        $categorySlug = (string) $request->string('category');
        $minPrice = $request->integer('min_price');
        $maxPrice = $request->integer('max_price');
        $sort = (string) $request->string('sort', 'latest');

        if ($keyword !== '' && config('scout.driver') === 'database' && DB::getDriverName() !== 'mysql') {
            $query = Product::query()->active()->with(['primaryImage', 'category', 'variants']);
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
            $this->applyCategoryFilter($query, $categorySlug);
            $this->applyPriceFilter($query, $minPrice, $maxPrice);
            $this->applySort($query, $sort);

            return $query->paginate($perPage)->withQueryString();
        }

        if ($keyword !== '') {
            $categoryId = $categorySlug === ''
                ? null
                : (Category::query()->where('slug', $categorySlug)->value('id') ?? -1);
            $search = Product::search($keyword)
                ->where('status', 'active')
                ->query(fn ($query) => $this->applySearchHydrationFilters($query, $categoryId, $minPrice, $maxPrice));

            if ($categoryId !== null) {
                $search->where('category_id', $categoryId);
            }

            if ($minPrice > 0) {
                $search->where('price', '>=', $minPrice);
            }

            if ($maxPrice > 0) {
                $search->where('price', '<=', $maxPrice);
            }

            match ($sort) {
                'price_asc' => $search->orderBy('price'),
                'price_desc' => $search->orderBy('price', 'desc'),
                'oldest' => $search->orderBy('created_at'),
                default => $search->orderBy('created_at', 'desc'),
            };

            return $search->paginate($perPage)->withQueryString();
        }

        $query = Product::query()->active()->with(['primaryImage', 'category', 'variants']);

        $this->applyCategoryFilter($query, $categorySlug);
        $this->applyPriceFilter($query, $minPrice, $maxPrice);
        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    private function applySearchHydrationFilters($query, mixed $categoryId, int $minPrice, int $maxPrice)
    {
        $query->active()->with(['primaryImage', 'category', 'variants']);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $this->applyPriceFilter($query, $minPrice, $maxPrice);

        return $query;
    }

    private function applyCategoryFilter($query, string $categorySlug): void
    {
        if ($categorySlug !== '') {
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $categorySlug));
        }
    }

    private function applyPriceFilter($query, int $minPrice, int $maxPrice): void
    {
        if ($minPrice > 0) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice > 0) {
            $query->where('price', '<=', $maxPrice);
        }
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };
    }
}
