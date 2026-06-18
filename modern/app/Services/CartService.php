<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CartService
{
    public function items(?User $user, string $sessionId): Collection
    {
        return CartItem::query()
            ->with(['product.images'])
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->when(!$user, fn ($query) => $query->where('session_id', $sessionId))
            ->get();
    }

    public function add(?User $user, string $sessionId, Product $product, int $quantity): CartItem
    {
        $quantity = max(1, $quantity);
        $identity = $user ? ['user_id' => $user->id] : ['session_id' => $sessionId];

        $item = CartItem::firstOrNew($identity + ['product_id' => $product->id]);
        $item->quantity = min($product->inventory, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();

        return $item;
    }

    public function update(CartItem $cartItem, int $quantity): CartItem
    {
        $cartItem->quantity = max(1, min($cartItem->product->inventory, $quantity));
        $cartItem->save();

        return $cartItem;
    }

    public function mergeSessionCart(User $user, string $sessionId): void
    {
        CartItem::where('session_id', $sessionId)->with('product')->get()->each(function (CartItem $guestItem) use ($user) {
            $existing = CartItem::firstOrNew([
                'user_id' => $user->id,
                'product_id' => $guestItem->product_id,
            ]);
            $existing->quantity = min(
                $guestItem->product->inventory,
                ($existing->exists ? $existing->quantity : 0) + $guestItem->quantity
            );
            $existing->session_id = null;
            $existing->save();
            $guestItem->delete();
        });
    }
}
