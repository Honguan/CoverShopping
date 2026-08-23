<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductRecommendationService
{
    public function popularProducts(int $limit = 8): Collection
    {
        // ponytail: rankings may lag sales by 10 minutes; invalidate on payment changes if fresher results become necessary.
        $productIds = Cache::remember("catalog.popular-product-ids.v1.{$limit}", now()->addMinutes(10), function () use ($limit) {
            return DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as sold_quantity'))
                ->whereNotNull('order_items.product_id')
                ->where('orders.payment_status', 'paid')
                ->where('orders.fulfillment_status', '!=', 'cancelled')
                ->groupBy('order_items.product_id')
                ->orderByDesc('sold_quantity')
                ->orderBy('order_items.product_id')
                ->limit($limit)
                ->pluck('order_items.product_id');
        });

        if ($productIds->isEmpty()) {
            return Product::active()->with(['primaryImage', 'variants'])->latest()->limit($limit)->get();
        }

        return $this->activeProductsInOrder($productIds);
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

        return $this->activeProductsInOrder($productIds);
    }

    public function relatedProducts(Product $product, int $limit = 4): Collection
    {
        return Product::active()
            ->with(['primaryImage', 'variants'])
            ->whereKeyNot($product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id)
                    ->orWhere('seller_id', $product->seller_id);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function recordRecentlyViewed(Product $product, ?int $userId, ?string $sessionId): void
    {
        DB::table('recently_viewed_products')->updateOrInsert(
            [
                'user_id' => $userId,
                'session_id' => $userId ? null : $sessionId,
                'product_id' => $product->id,
            ],
            ['viewed_at' => now()]
        );
    }

    private function activeProductsInOrder(Collection $productIds): Collection
    {
        return Product::active()
            ->with(['primaryImage', 'variants'])
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();
    }
}
