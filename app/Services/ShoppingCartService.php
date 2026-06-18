<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

class ShoppingCartService
{
    public function getItemsForUserOrSession(?User $user, string $sessionId): Collection
    {
        return CartItem::query()
            ->with(['product.images', 'variant'])
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->when(!$user, fn ($query) => $query->where('session_id', $sessionId))
            ->get();
    }

    public function addProduct(?User $user, string $sessionId, Product $product, int $quantity, ?ProductVariant $variant = null): CartItem
    {
        $quantity = max(1, $quantity);
        $identity = $user ? ['user_id' => $user->id] : ['session_id' => $sessionId];
        $availableInventory = $variant ? $variant->inventory : $product->inventory;

        $item = CartItem::firstOrNew($identity + [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);
        $item->quantity = min($availableInventory, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();

        return $item;
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): CartItem
    {
        $availableInventory = $cartItem->variant ? $cartItem->variant->inventory : $cartItem->product->inventory;
        $cartItem->quantity = max(1, min($availableInventory, $quantity));
        $cartItem->save();

        return $cartItem;
    }

    public function mergeGuestCartIntoUserCart(User $user, string $sessionId): void
    {
        CartItem::where('session_id', $sessionId)->with(['product', 'variant'])->get()->each(function (CartItem $guestItem) use ($user) {
            $existing = CartItem::firstOrNew([
                'user_id' => $user->id,
                'product_id' => $guestItem->product_id,
                'product_variant_id' => $guestItem->product_variant_id,
            ]);
            $existing->quantity = min(
                $guestItem->variant ? $guestItem->variant->inventory : $guestItem->product->inventory,
                ($existing->exists ? $existing->quantity : 0) + $guestItem->quantity
            );
            $existing->session_id = null;
            $existing->save();
            $guestItem->delete();
        });
    }
}
