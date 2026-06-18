<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Queries\ProductCatalogQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogController extends Controller
{
    public function showProductList(Request $request, ProductCatalogQuery $productCatalogQuery)
    {
        return view('catalog.index', [
            'products' => $productCatalogQuery->paginate($request),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function showProductDetail(Request $request, Product $product)
    {
        abort_unless($product->status === 'active', 404);

        $user = $request->user();

        DB::table('recently_viewed_products')->updateOrInsert(
            [
                'user_id' => $user?->id,
                'session_id' => $user ? null : $request->session()->getId(),
                'product_id' => $product->id,
            ],
            ['viewed_at' => now()]
        );

        return view('catalog.show', [
            'product' => $product->load(['images', 'variants', 'category', 'seller', 'reviews.user', 'questions.user', 'questions.answers.user']),
        ]);
    }
}
