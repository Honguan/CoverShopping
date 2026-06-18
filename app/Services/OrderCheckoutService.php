<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderCheckoutService
{
    public function __construct(private CouponDiscountService $couponDiscountService, private ProductPricingService $productPricingService)
    {
    }

    public function createOrderFromCart(User $user, ?int $shippingMethodId = null, ?int $addressId = null, ?string $couponCode = null): Order
    {
        $user->loadMissing('businessProfile');

        return DB::transaction(function () use ($user, $shippingMethodId, $addressId, $couponCode) {
            $cartItems = CartItem::where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new RuntimeException('購物車沒有商品');
            }

            $products = Product::whereKey($cartItems->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variants = ProductVariant::whereKey($cartItems->pluck('product_variant_id')->filter()->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($cartItems as $cartItem) {
                $product = $products->get($cartItem->product_id);
                $variant = $cartItem->product_variant_id ? $variants->get($cartItem->product_variant_id) : null;

                if (!$product || $product->status !== 'active') {
                    throw new RuntimeException('商品目前無法購買');
                }

                if ($cartItem->product_variant_id && (!$variant || !$variant->is_active || $variant->product_id !== $product->id)) {
                    throw new RuntimeException("商品 {$product->name} 規格無法購買");
                }

                $availableInventory = $variant ? $variant->inventory : $product->inventory;
                if ($availableInventory < $cartItem->quantity) {
                    throw new RuntimeException("商品 {$product->name} 庫存不足");
                }

                $unitPrice = $this->productPricingService->calculateUnitPrice($product, $variant, $user, $cartItem->quantity, true);
                $lineSubtotal = $unitPrice * $cartItem->quantity;
                $subtotal += $lineSubtotal;

                $lines[$cartItem->id] = [
                    'cart_item' => $cartItem,
                    'product' => $product,
                    'variant' => $variant,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $coupon = $this->couponDiscountService->findUsableCoupon($couponCode, $subtotal, $user);
            $discountTotal = $coupon ? $this->couponDiscountService->calculateDiscount($coupon, $subtotal) : 0;
            $shippingMethod = $shippingMethodId
                ? ShippingMethod::whereKey($shippingMethodId)->where('is_active', true)->firstOrFail()
                : null;
            $shippingFee = $shippingMethod ? $shippingMethod->fee : 0;

            $order = Order::create([
                'number' => $this->newOrderNumber(),
                'user_id' => $user->id,
                'address_id' => $addressId,
                'coupon_id' => $coupon?->id,
                'shipping_method_id' => $shippingMethod?->id,
                'sales_channel' => $this->productPricingService->detectSalesChannel($user),
                'coupon_code' => $coupon?->code,
                'shipping_method_name' => $shippingMethod?->name,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_fee' => $shippingFee,
                'total' => $subtotal - $discountTotal + $shippingFee,
            ]);

            foreach ($lines as $line) {
                /** @var CartItem $cartItem */
                $cartItem = $line['cart_item'];
                /** @var Product $product */
                $product = $line['product'];
                /** @var ProductVariant|null $variant */
                $variant = $line['variant'];

                if ($variant) {
                    $variant->inventory -= $cartItem->quantity;
                    $variant->save();
                    $inventoryAfter = $variant->inventory;
                } else {
                    $product->inventory -= $cartItem->quantity;
                    $product->save();
                    $inventoryAfter = $product->inventory;
                }

                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->displayName(),
                    'unit_price' => $line['unit_price'],
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $line['subtotal'],
                ]);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $user->id,
                    'reason' => 'order_created',
                    'quantity_delta' => -1 * $cartItem->quantity,
                    'inventory_after' => $inventoryAfter,
                    'reference_type' => get_class($orderItem),
                    'reference_id' => $orderItem->id,
                ]);
            }

            if ($coupon) {
                $coupon->increment('used_count');
                $coupon->redemptions()->create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ]);
            }

            CartItem::whereKey($cartItems->modelKeys())->delete();

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
