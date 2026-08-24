<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductReviewRequest;
use App\Models\Product;
use App\Services\ProductReviewService;

class ProductReviewController extends Controller
{
    public function createProductReview(CreateProductReviewRequest $request, Product $product, ProductReviewService $reviews)
    {
        abort_unless($product->status === 'active', 404);

        $data = $request->validated();
        $reviews->create(
            $request->user(),
            $product,
            (int) $data['order_item_id'],
            (int) $data['rating'],
            $data['content'] ?? null,
            $request
        );

        return back()->with('status', __('ui.review_submitted'));
    }
}
