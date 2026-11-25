<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogQuery
{
    public function paginateFavorites(User $user): LengthAwarePaginator
    {
        return Product::query()
            ->select('products.*')
            ->join('favorites', 'favorites.product_id', '=', 'products.id')
            ->where('favorites.user_id', $user->id)
            ->with(['primaryImage', 'variants'])
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('favorites.id')
            ->paginate(24);
    }

    public function paginate(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $keyword = trim((string) $request->string('q'));
        $categorySlug = (string) $request->string('category');
        $minPrice = $request->integer('min_price');
        $maxPrice = $request->integer('max_price');
        $sort = (string) $request->string('sort', $keyword !== '' ? 'relevance' : 'latest');
        $categoryIds = $this->categoryIds($categorySlug);

        // An exact SKU (variant code) jumps straight to its product, whatever the search engine indexes.
        $skuProductIds = $keyword === '' ? collect() : ProductVariant::where('sku', $keyword)->pluck('product_id');

        if ($skuProductIds->isNotEmpty()) {
            $query = Product::query()->active()->with(['primaryImage', 'category', 'variants'])->whereKey($skuProductIds);
            $this->applyCategoryFilter($query, $categoryIds);
            $this->applyPriceFilter($query, $minPrice, $maxPrice);
            $this->applySort($query, $sort);

            return $query->paginate($perPage)->withQueryString();
        }

        if ($keyword !== '' && config('scout.driver') === 'database' && DB::getDriverName() !== 'mysql') {
            $query = Product::query()->active()->with(['primaryImage', 'category', 'variants']);
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
            $this->applyCategoryFilter($query, $categoryIds);
            $this->applyPriceFilter($query, $minPrice, $maxPrice);
            $this->applySort($query, $sort);

            return $query->paginate($perPage)->withQueryString();
        }

        if ($keyword !== '') {
            $search = Product::search($keyword)
                ->where('status', 'active')
                ->query(fn ($query) => $this->applySearchHydrationFilters($query, $categoryIds, $minPrice, $maxPrice));

            if ($categoryIds !== null) {
                $search->whereIn('category_id', $categoryIds);
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
                // Leave the order to the search engine so the best matches come first.
                'relevance' => $search,
                default => $search->orderBy('created_at', 'desc'),
            };

            return $search->paginate($perPage)->withQueryString();
        }

        $query = Product::query()->active()->with(['primaryImage', 'category', 'variants']);

        $this->applyCategoryFilter($query, $categoryIds);
        $this->applyPriceFilter($query, $minPrice, $maxPrice);
        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * The chosen category plus its subcategories; null when no category is chosen, [-1] when the slug is unknown.
     *
     * @return list<int>|null
     */
    private function categoryIds(string $categorySlug): ?array
    {
        if ($categorySlug === '') {
            return null;
        }

        $categoryId = Category::query()->where('slug', $categorySlug)->value('id');

        if ($categoryId === null) {
            return [-1];
        }

        return [$categoryId, ...Category::query()->where('parent_id', $categoryId)->pluck('id')->all()];
    }

    /** @param list<int>|null $categoryIds */
    private function applySearchHydrationFilters($query, ?array $categoryIds, int $minPrice, int $maxPrice)
    {
        $query->active()->with(['primaryImage', 'category', 'variants']);
        $this->applyCategoryFilter($query, $categoryIds);

        $this->applyPriceFilter($query, $minPrice, $maxPrice);

        return $query;
    }

    /** @param list<int>|null $categoryIds */
    private function applyCategoryFilter($query, ?array $categoryIds): void
    {
        if ($categoryIds !== null) {
            $query->whereIn('category_id', $categoryIds);
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

        // 同價格或同時間的商品需要固定順序，否則分頁會重複或漏掉商品。
        $query->orderBy('id', in_array($sort, ['price_asc', 'oldest'], true) ? 'asc' : 'desc');
    }
}
