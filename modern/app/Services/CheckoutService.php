<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutService
{
    public function createOrder(User $user, int $shippingFee = 0, ?int $addressId = null): Order
    {
        return DB::transaction(function () use ($user, $shippingFee, $addressId) {
            $cartItems = CartItem::where('user_id', $user->id)->with('product')->lockForUpdate()->get();

            if ($cartItems->isEmpty()) {
                throw new RuntimeException('購物車沒有商品');
            }

            $subtotal = 0;
            $lockedProducts = [];

            foreach ($cartItems as $cartItem) {
                $product = Product::whereKey($cartItem->product_id)->lockForUpdate()->firstOrFail();

                if ($product->status !== 'active') {
                    throw new RuntimeException("商品 {$product->name} 尚未上架");
                }

                if ($product->inventory < $cartItem->quantity) {
                    throw new RuntimeException("商品 {$product->name} 庫存不足");
                }

                $subtotal += $product->price * $cartItem->quantity;
                $lockedProducts[$cartItem->product_id] = $product;
            }

            $order = Order::create([
                'number' => $this->newOrderNumber(),
                'user_id' => $user->id,
                'address_id' => $addressId,
                'subtotal' => $subtotal,
                'shipping_fee' => max(0, $shippingFee),
                'total' => $subtotal + max(0, $shippingFee),
            ]);

            foreach ($cartItems as $cartItem) {
                $product = $lockedProducts[$cartItem->product_id];
                $product->decrement('inventory', $cartItem->quantity);

                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $product->price * $cartItem->quantity,
                ]);
            }

            CartItem::where('user_id', $user->id)->delete();

            return $order->load('items');
        }, 3);
    }

    private function newOrderNumber(): string
    {
        do {
            $number = now()->format('YmdHis') . strtoupper(Str::random(6));
        } while (Order::where('number', $number)->exists());

        return $number;
    }
}
