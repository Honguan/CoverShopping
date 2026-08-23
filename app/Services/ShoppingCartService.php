<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShoppingCartService
{
    public function getItemsForUserOrSession(?User $user, string $sessionId): Collection
    {
        return CartItem::query()
            ->with(['product.images', 'variant'])
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->when(! $user, fn ($query) => $query->where('session_id', $sessionId))
            ->get();
    }

    public function addProduct(?User $user, string $sessionId, Product $product, int $quantity, ?ProductVariant $variant = null): CartItem
    {
        $this->addAvailableQuantity($user, $sessionId, $product, $quantity, $variant);

        return CartItem::query()
            ->where($this->cartIdentity($user, $sessionId))
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->firstOrFail();
    }

    public function addAvailableQuantity(?User $user, string $sessionId, Product $product, int $quantity, ?ProductVariant $variant = null): int
    {
        if ($variant && (! $variant->is_active || $variant->product_id !== $product->id)) {
            throw new RuntimeException('Product variant is unavailable.');
        }

        if (! $variant && $product->variants()->exists()) {
            throw new RuntimeException('Select a product variant.');
        }

        $quantity = max(1, $quantity);
        $availableInventory = $this->availableInventory($product, $variant);

        if ($availableInventory < 1) {
            return 0;
        }

        $item = CartItem::firstOrNew($this->cartIdentity($user, $sessionId) + [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);

        $currentQuantity = $item->exists ? $item->quantity : 0;
        $nextQuantity = min($availableInventory, $currentQuantity + $quantity);
        $addedQuantity = max(0, $nextQuantity - $currentQuantity);

        if ($addedQuantity > 0) {
            $item->quantity = $nextQuantity;
            $item->save();
        }

        return $addedQuantity;
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): CartItem
    {
        $availableInventory = $this->availableInventory($cartItem->product, $cartItem->variant);
        $cartItem->quantity = max(1, min($availableInventory, $quantity));
        $cartItem->save();

        return $cartItem;
    }

    public function mergeGuestCartIntoUserCart(User $user, string $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId): void {
            $guestItems = CartItem::where('session_id', $sessionId)
                ->with(['product', 'variant'])
                ->lockForUpdate()
                ->get();

            if ($guestItems->isEmpty()) {
                return;
            }

            $existingItems = CartItem::where('user_id', $user->id)
                ->whereIn('product_id', $guestItems->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (CartItem $item) => $this->cartKey($item->product_id, $item->product_variant_id));

            $guestItems->each(function (CartItem $guestItem) use ($user, $existingItems) {
                $key = $this->cartKey($guestItem->product_id, $guestItem->product_variant_id);
                $existing = $existingItems->get($key) ?? new CartItem([
                    'user_id' => $user->id,
                    'product_id' => $guestItem->product_id,
                    'product_variant_id' => $guestItem->product_variant_id,
                ]);
                $existing->quantity = min(
                    $this->availableInventory($guestItem->product, $guestItem->variant),
                    ($existing->exists ? $existing->quantity : 0) + $guestItem->quantity
                );
                $existing->session_id = null;
                $existing->save();
                $guestItem->delete();
            });
        }, 3);
    }

    public function clearItemsForUserOrSession(?User $user, string $sessionId): int
    {
        return CartItem::query()
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->when(! $user, fn ($query) => $query->where('session_id', $sessionId))
            ->delete();
    }

    public function statusMessagesForItem(CartItem $cartItem, ?User $user): array
    {
        $product = $cartItem->product;
        $variant = $cartItem->variant;
        $messages = [];

        if (! $product || $product->status !== 'active') {
            return ['Product is inactive. Remove it before checkout.'];
        }

        if ($cartItem->product_variant_id && (! $variant || ! $variant->is_active || $variant->product_id !== $product->id)) {
            return ['Product variant is unavailable. Remove it and choose again.'];
        }

        $availableInventory = $this->availableInventory($product, $variant);

        if ($availableInventory < 1) {
            $messages[] = 'Out of stock. Remove it before checkout.';
        } elseif ($availableInventory < $cartItem->quantity) {
            $messages[] = 'Only '.$availableInventory.' in stock. Please update quantity.';
        }

        if ($user?->canUseBusinessPricing() && $product->business_price !== null && $cartItem->quantity < $product->business_min_quantity) {
            $messages[] = 'Business minimum quantity: '.$product->business_min_quantity;
        }

        return $messages;
    }

    public function statusMessagesForItems(Collection $cartItems, ?User $user): array
    {
        return $cartItems
            ->mapWithKeys(fn (CartItem $cartItem) => [$cartItem->id => $this->statusMessagesForItem($cartItem, $user)])
            ->all();
    }

    private function cartIdentity(?User $user, string $sessionId): array
    {
        return $user ? ['user_id' => $user->id] : ['session_id' => $sessionId];
    }

    private function availableInventory(Product $product, ?ProductVariant $variant): int
    {
        return $variant ? $variant->inventory : $product->inventory;
    }

    private function cartKey(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?: 'default');
    }
}
