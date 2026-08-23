<?php

namespace App\Queries;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ProductCatalogQuery
{
    public function paginate(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $keyword = trim((string) $request->string('q'));
        $categorySlug = (string) $request->string('category');
        $minPrice = $request->integer('min_price');
        $maxPrice = $request->integer('max_price');
        $sort = (string) $request->string('sort', 'latest');

        if ($keyword !== '' && config('scout.driver') !== 'database') {
            return Product::search($keyword)
                ->query(fn ($query) => $this->applyCommonFilters($query, $categorySlug, $minPrice, $maxPrice, $sort))
                ->paginate($perPage)
                ->withQueryString();
        }

        $query = Product::query()->active()->with(['primaryImage', 'category', 'variants']);

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        $this->applyCategoryFilter($query, $categorySlug);
        $this->applyPriceFilter($query, $minPrice, $maxPrice);
        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    private function applyCommonFilters($query, string $categorySlug, int $minPrice, int $maxPrice, string $sort)
    {
        $query->active()->with(['primaryImage', 'category', 'variants']);
        $this->applyCategoryFilter($query, $categorySlug);
        $this->applyPriceFilter($query, $minPrice, $maxPrice);
        $this->applySort($query, $sort);

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
