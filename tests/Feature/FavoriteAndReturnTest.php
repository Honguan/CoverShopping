<?php

namespace Tests\Feature;

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

    public function test_customer_can_request_return_for_own_completed_order(): void
    {
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
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

    public function test_customer_cannot_request_return_twice(): void
    {
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'T202606180002',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
            'return_status' => 'requested',
        ]);

        $this->actingAs($buyer)
            ->post("/orders/{$order->id}/returns", ['reason' => '重複申請'])
            ->assertStatus(409);
    }

    public function test_customer_can_review_purchased_completed_product(): void
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
            'name' => 'Review Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'T202606180003',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $this->actingAs($buyer)
            ->post("/products/{$product->id}/reviews", [
                'order_item_id' => $item->id,
                'rating' => 5,
                'content' => '品質穩定',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'order_item_id' => $item->id,
            'rating' => 5,
        ]);
    }

    public function test_customer_question_notifies_seller_and_seller_can_answer(): void
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
            'name' => 'Question Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->post("/products/{$product->id}/questions", ['question' => '今天下單何時出貨？'])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $seller->id,
            'type' => 'product_question',
        ]);

        $questionId = $product->questions()->first()->id;
        $this->actingAs($seller)
            ->post("/seller/questions/{$questionId}/answers", ['answer' => '今日可出貨'])
            ->assertRedirect();

        $this->assertDatabaseHas('product_question_answers', [
            'product_question_id' => $questionId,
            'user_id' => $seller->id,
            'answer' => '今日可出貨',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $buyer->id,
            'type' => 'product_question_answered',
        ]);
    }
}
