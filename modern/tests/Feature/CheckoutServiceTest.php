<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
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

        $order = app(CheckoutService::class)->createOrder($buyer, 30);

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

        app(CheckoutService::class)->createOrder($buyer);
    }
}
