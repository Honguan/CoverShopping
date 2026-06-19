<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Queries\ProductCatalogQuery;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    public function showProductList(Request $request, ProductCatalogQuery $productCatalogQuery, ProductRecommendationService $recommendations)
    {
        $user = $request->user();

        return view('catalog.index', [
            'products' => $productCatalogQuery->paginate($request),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'popularProducts' => $recommendations->popularProducts(),
            'recentlyViewedProducts' => $recommendations->recentlyViewed($user?->id, $user ? null : $request->session()->getId()),
        ]);
    }

    public function showProductDetail(Request $request, Product $product, ProductRecommendationService $recommendations)
    {
        abort_unless($product->status === 'active', 404);

        $user = $request->user();

        $recommendations->recordRecentlyViewed($product, $user?->id, $user ? null : $request->session()->getId());

        return view('catalog.show', [
            'product' => $product->load(['images', 'variants', 'category', 'seller', 'reviews.user', 'questions.user', 'questions.answers.user']),
            'relatedProducts' => $recommendations->relatedProducts($product),
        ]);
    }
}
