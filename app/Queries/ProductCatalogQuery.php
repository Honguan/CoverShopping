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

        if ($keyword !== '' && config('scout.driver') !== 'database') {
            return Product::search($keyword)
                ->query(fn ($query) => $this->applyCommonFilters($query, $categorySlug))
                ->paginate($perPage)
                ->withQueryString();
        }

        $query = Product::query()->active()->with(['primaryImage', 'category']);

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    private function applyCommonFilters($query, string $categorySlug)
    {
        $query->active()->with(['primaryImage', 'category']);
        $this->applyCategoryFilter($query, $categorySlug);

        return $query;
    }

    private function applyCategoryFilter($query, string $categorySlug): void
    {
        if ($categorySlug !== '') {
            $query->whereHas('category', fn ($builder) => $builder->where('slug', $categorySlug));
        }
    }
}
