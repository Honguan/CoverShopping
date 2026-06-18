<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteAndReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_favorite_active_product(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Favorite Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)->post("/products/{$product->id}/favorite")->assertRedirect();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_customer_can_manage_address_book(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();

        $this->actingAs($buyer)->post('/addresses', [
            'recipient_name' => 'Buyer',
            'phone' => '0911000000',
            'postal_code' => '100',
            'city' => 'Taipei',
            'district' => 'Da-an',
            'address_line' => 'No. 1',
            'is_default' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $buyer->id,
            'recipient_name' => 'Buyer',
            'is_default' => true,
        ]);
    }

    public function test_customer_can_reorder_available_products(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Reorder Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'T202606180001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $this->actingAs($buyer)->post("/orders/{$order->id}/reorder")->assertRedirect('/cart');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_can_request_return_for_own_completed_order(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $order = Order::create([
            'number' => 'T202606180002',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);

        $this->actingAs($buyer)
            ->post("/orders/{$order->id}/returns", ['reason' => '商品不符合需求'])
            ->assertRedirect();

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'status' => 'requested',
        ]);
        $this->assertSame('requested', $order->fresh()->return_status);
    }

    public function test_customer_cannot_delete_other_users_address(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $other = User::create([
            'name' => 'Other',
            'account' => 'other',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $address = Address::create([
            'user_id' => $other->id,
            'recipient_name' => 'Other',
            'phone' => '0922000000',
            'city' => 'Taipei',
            'address_line' => 'No. 9',
        ]);

        $this->actingAs($buyer)->delete("/addresses/{$address->id}")->assertForbidden();
    }

    private function createSellerAndBuyer(): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-' . uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer-' . uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        return [$seller, $buyer];
    }
}
