<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductFavoriteService
{
    public function add(User $user, Product $product): void
    {
        $now = now();

        DB::table('favorites')->updateOrInsert([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ], [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function remove(User $user, Product $product): void
    {
        DB::table('favorites')
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();
    }
}
