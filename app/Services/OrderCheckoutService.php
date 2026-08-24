<?php

namespace App\Services;

use App\Models\Address;
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
    public function __construct(
        private CouponDiscountService $couponDiscountService,
        private ProductPricingService $productPricingService,
        private PromotionService $promotionService
    ) {}

    public function createOrderFromCart(User $user, ?int $shippingMethodId = null, ?int $addressId = null, ?string $couponCode = null, ?string $purchaseOrderNumber = null): Order
    {
        $user->loadMissing('businessProfile');

        return DB::transaction(function () use ($user, $shippingMethodId, $addressId, $couponCode, $purchaseOrderNumber) {
            $cartItemIds = CartItem::where('user_id', $user->id)->pluck('id');

            if ($cartItemIds->isEmpty()) {
                throw new RuntimeException(__('ui.cart_empty'));
            }

            $cartSnapshot = CartItem::whereKey($cartItemIds)->get();
            $products = Product::whereKey($cartSnapshot->pluck('product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variants = ProductVariant::whereKey($cartSnapshot->pluck('product_variant_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $cartItems = CartItem::where('user_id', $user->id)
                ->whereKey($cartItemIds)
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new RuntimeException(__('ui.cart_empty'));
            }

            $subtotal = 0;
            $lines = [];

            foreach ($cartItems->groupBy(fn (CartItem $item) => $item->product_id.':'.($item->product_variant_id ?? 0)) as $items) {
                $cartItem = $items->first();
                $quantity = $items->sum('quantity');
                $product = $products->get($cartItem->product_id);
                $variant = $cartItem->product_variant_id ? $variants->get($cartItem->product_variant_id) : null;

                if (! $product || $product->status !== 'active') {
                    throw new RuntimeException(__('ui.product_inactive_checkout'));
                }

                if ($cartItem->product_variant_id && (! $variant || ! $variant->is_active || $variant->product_id !== $product->id)) {
                    throw new RuntimeException(__('ui.product_variant_unavailable_checkout'));
                }

                $availableInventory = $variant ? $variant->inventory : $product->inventory;
                if ($availableInventory < $quantity) {
                    throw new RuntimeException(__('ui.stock_quantity_available', ['quantity' => $availableInventory]));
                }

                $unitPrice = $this->productPricingService->calculateUnitPrice($product, $variant, $user, $quantity, true);
                $lineSubtotal = $unitPrice * $quantity;
                $subtotal += $lineSubtotal;

                $lines[$cartItem->id] = [
                    'cart_item' => $cartItem,
                    'product' => $product,
                    'variant' => $variant,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $coupon = $this->couponDiscountService->findUsableCoupon($couponCode, $subtotal, $user);
            $couponDiscount = $coupon ? $this->couponDiscountService->calculateDiscount($coupon, $subtotal) : 0;
            $promotionDiscount = $this->promotionService->calculateOrderDiscount($subtotal);
            $discountTotal = min($subtotal, $couponDiscount + $promotionDiscount);
            $shippingMethod = $shippingMethodId
                ? ShippingMethod::whereKey($shippingMethodId)->where('is_active', true)->firstOrFail()
                : null;
            $shippingAddressSnapshot = $addressId
                ? Address::where('user_id', $user->id)->lockForUpdate()->findOrFail($addressId)
                    ->only(['recipient_name', 'phone', 'postal_code', 'city', 'district', 'address_line'])
                : null;
            $shippingFee = $this->promotionService->calculateShippingFee($shippingMethod, $subtotal);

            $salesChannel = $this->productPricingService->detectSalesChannel($user);
            $businessProfileSnapshot = $salesChannel === 'b2b'
                ? $user->businessProfile?->only(['company_name', 'tax_id', 'contact_name', 'contact_phone', 'billing_email'])
                : null;

            $order = Order::create([
                'number' => $this->newOrderNumber(),
                'user_id' => $user->id,
                'address_id' => $addressId,
                'shipping_address_snapshot' => $shippingAddressSnapshot,
                'coupon_id' => $coupon?->id,
                'shipping_method_id' => $shippingMethod?->id,
                'sales_channel' => $salesChannel,
                'purchase_order_number' => $salesChannel === 'b2b' ? $purchaseOrderNumber : null,
                'business_profile_snapshot' => $businessProfileSnapshot,
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
                    $variant->inventory -= $line['quantity'];
                    $variant->save();
                    $inventoryAfter = $variant->inventory;
                } else {
                    $product->inventory -= $line['quantity'];
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
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                ]);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $user->id,
                    'reason' => 'order_created',
                    'quantity_delta' => -1 * $line['quantity'],
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
            $number = now()->format('YmdHis').strtoupper(Str::random(6));
        } while (Order::where('number', $number)->exists());

        return $number;
    }
}
