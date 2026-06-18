<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductReviewRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AuditLogService;

class ProductReviewController extends Controller
{
    public function createProductReview(CreateProductReviewRequest $request, Product $product, AuditLogService $auditLogService)
    {
        abort_unless($product->status === 'active', 404);

        $data = $request->validated();
        $orderItem = OrderItem::with('order')
            ->where('product_id', $product->id)
            ->whereKey($data['order_item_id'])
            ->firstOrFail();

        $this->authorize('review', $orderItem);

        $review = $product->reviews()->create([
            'user_id' => $request->user()->id,
            'order_item_id' => $orderItem->id,
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'status' => 'published',
        ]);

        $auditLogService->writeLog('product.review.created', $review, ['product' => $product->id], $request);

        return back()->with('status', 'Review submitted.');
    }
}
