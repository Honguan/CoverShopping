<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductFavoriteController extends Controller
{
    public function addProductToFavorites(Request $request, Product $product)
    {
        abort_unless($product->status === 'active', 404);

        $now = now();

        DB::table('favorites')->updateOrInsert([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ], [
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return back()->with('status', '已加入收藏');
    }

    public function removeProductFromFavorites(Request $request, Product $product)
    {
        DB::table('favorites')
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('status', '已取消收藏');
    }
}
