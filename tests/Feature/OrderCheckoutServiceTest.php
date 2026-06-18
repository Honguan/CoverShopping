<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\BusinessProfile;
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
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
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

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer, 30);

        $this->assertSame(230, $order->total);
        $this->assertSame(0, $product->fresh()->inventory);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Stable Product',
            'unit_price' => 100,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $buyer->id]);
    }

    public function test_checkout_rejects_insufficient_inventory(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
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

    public function test_checkout_applies_coupon_and_records_inventory_movement(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Coupon Product',
            'price' => 200,
            'inventory' => 3,
            'status' => 'active',
        ]);
        Coupon::create([
            'code' => 'SAVE50',
            'name' => 'Save 50',
            'type' => 'fixed',
            'value' => 50,
            'minimum_subtotal' => 100,
            'is_active' => true,
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer, 20, null, 'SAVE50');

        $this->assertSame(50, $order->discount_total);
        $this->assertSame(170, $order->total);
        $this->assertDatabaseHas('coupon_redemptions', [
            'user_id' => $buyer->id,
            'order_id' => $order->id,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'reason' => 'order_created',
            'quantity_delta' => -1,
            'inventory_after' => 2,
        ]);
    }

    public function test_checkout_uses_variant_inventory_and_shipping_method_snapshot(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
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
            'option_name' => '顏色',
            'option_value' => '紅色',
            'price_delta' => 30,
            'inventory' => 2,
            'is_active' => true,
        ]);
        $shippingMethod = ShippingMethod::create([
            'name' => '超商取貨',
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
        $this->assertSame('超商取貨', $order->shipping_method_name);
        $this->assertSame(1, $variant->fresh()->inventory);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'variant_name' => '顏色: 紅色',
            'unit_price' => 230,
        ]);
    }

    public function test_approved_business_user_gets_business_price(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-b2b',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Business Buyer',
            'account' => 'business-buyer',
            'password' => 'password',
            'role' => 'customer',
            'account_type' => 'b2b',
            'status' => 'active',
        ]);
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

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer);

        $this->assertSame('b2b', $order->sales_channel);
        $this->assertSame(400, $order->subtotal);
        $this->assertSame(400, $order->total);
    }

    public function test_business_price_requires_minimum_quantity(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-min',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Business Buyer',
            'account' => 'business-min',
            'password' => 'password',
            'role' => 'customer',
            'account_type' => 'b2b',
            'status' => 'active',
        ]);
        BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Min Ltd',
            'tax_id' => '87654321',
            'contact_name' => 'Buyer',
            'contact_phone' => '0922000000',
            'status' => 'approved',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Minimum Product',
            'price' => 100,
            'business_price' => 80,
            'business_min_quantity' => 5,
            'inventory' => 10,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $this->expectException(RuntimeException::class);

        app(OrderCheckoutService::class)->createOrderFromCart($buyer);
    }
}
