<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductReviewService
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function create(User $user, Product $product, int $orderItemId, int $rating, ?string $content = null, ?Request $request = null): ProductReview
    {
        $orderItem = OrderItem::with('order')
            ->where('product_id', $product->id)
            ->whereKey($orderItemId)
            ->firstOrFail();

        Gate::forUser($user)->authorize('review', $orderItem);

        $review = $product->reviews()->create([
            'user_id' => $user->id,
            'order_item_id' => $orderItem->id,
            'rating' => $rating,
            'content' => $content,
            'status' => 'published',
        ]);

        $this->auditLogService->writeLog('product.review.created', $review, ['product' => $product->id], $request);

        return $review;
    }
}
