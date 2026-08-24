<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductFavoriteService;
use Illuminate\Http\Request;

class ProductFavoriteController extends Controller
{
    public function addProductToFavorites(Request $request, Product $product, ProductFavoriteService $favorites)
    {
        abort_unless($product->status === 'active', 404);

        $favorites->add($request->user(), $product);

        return back()->with('status', __('ui.product_added_to_favorites'));
    }

    public function removeProductFromFavorites(Request $request, Product $product, ProductFavoriteService $favorites)
    {
        $favorites->remove($request->user(), $product);

        return back()->with('status', __('ui.product_removed_from_favorites'));
    }
}
