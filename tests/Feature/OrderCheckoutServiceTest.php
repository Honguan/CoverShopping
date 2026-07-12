<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\OrderCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order_snapshot_and_decrements_inventory(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Stable Product',
            'price' => 100,
            'inventory' => 2,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer);

        $this->assertSame(200, $order->total);
        $this->assertSame(0, $product->fresh()->inventory);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Stable Product',
            'unit_price' => 100,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $buyer->id]);
    }

    public function test_checkout_applies_coupon_full_discount_and_free_shipping(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Promotion Product',
            'price' => 1500,
            'inventory' => 3,
            'status' => 'active',
        ]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Home Delivery',
            'fee' => 80,
            'is_active' => true,
        ]);
        Coupon::create([
            'code' => 'SAVE100',
            'name' => 'Save 100',
            'type' => 'fixed',
            'value' => 100,
            'minimum_subtotal' => 100,
            'is_active' => true,
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer, $shippingMethod->id, null, 'SAVE100');

        $this->assertSame(3000, $order->subtotal);
        $this->assertSame(300, $order->discount_total);
        $this->assertSame(0, $order->shipping_fee);
        $this->assertSame(2700, $order->total);
    }

    public function test_checkout_rejects_insufficient_inventory(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Low Stock Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->expectException(RuntimeException::class);

        app(OrderCheckoutService::class)->createOrderFromCart($buyer);
    }

    public function test_checkout_uses_variant_inventory_and_shipping_method_snapshot(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Variant Product',
            'price' => 200,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VP-RED',
            'option_name' => 'Color',
            'option_value' => 'Red',
            'price_delta' => 30,
            'inventory' => 2,
            'is_active' => true,
        ]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Home Delivery',
            'fee' => 60,
            'is_active' => true,
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer, $shippingMethod->id);

        $this->assertSame(290, $order->total);
        $this->assertSame('Home Delivery', $order->shipping_method_name);
        $this->assertSame(1, $variant->fresh()->inventory);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Color: Red',
            'unit_price' => 230,
        ]);
    }

    public function test_approved_business_user_gets_business_price(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer(['account_type' => 'b2b']);
        BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Acme Ltd',
            'tax_id' => '12345678',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'status' => 'approved',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'B2B Product',
            'price' => 100,
            'business_price' => 80,
            'business_min_quantity' => 5,
            'inventory' => 10,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer, null, null, null, 'PO-ACME-2026-001');

        $this->assertSame('b2b', $order->sales_channel);
        $this->assertSame('PO-ACME-2026-001', $order->purchase_order_number);
        $this->assertSame([
            'company_name' => 'Acme Ltd',
            'tax_id' => '12345678',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'billing_email' => null,
        ], $order->business_profile_snapshot);
        $this->assertSame(400, $order->subtotal);
        $this->assertSame(400, $order->total);
    }

    private function createSellerAndBuyer(array $buyerOverrides = []): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-' . uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create($buyerOverrides + [
            'name' => 'Buyer',
            'account' => 'buyer-' . uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        return [$seller, $buyer];
    }
}
