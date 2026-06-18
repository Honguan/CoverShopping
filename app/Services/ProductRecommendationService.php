<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRecommendationService
{
    public function popularProducts(int $limit = 8): Collection
    {
        $productIds = DB::table('order_items')
            ->select('product_id', DB::raw('SUM(quantity) as sold_quantity'))
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('sold_quantity')
            ->limit($limit)
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return Product::active()->with('primaryImage')->latest()->limit($limit)->get();
        }

        return Product::active()
            ->with('primaryImage')
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();
    }

    public function recentlyViewed(?int $userId, ?string $sessionId, int $limit = 8): Collection
    {
        $query = DB::table('recently_viewed_products')
            ->select('product_id')
            ->orderByDesc('viewed_at')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $productIds = $query->pluck('product_id');

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::active()
            ->with('primaryImage')
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();
    }

    public function relatedProducts(Product $product, int $limit = 4): Collection
    {
        return Product::active()
            ->with('primaryImage')
            ->whereKeyNot($product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id)
                    ->orWhere('seller_id', $product->seller_id);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }
}
