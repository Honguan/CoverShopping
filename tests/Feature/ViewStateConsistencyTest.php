<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewStateConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_question_form_targets_the_viewed_product_when_related_products_exist(): void
    {
        $seller = $this->user('seller', 'seller');
        $product = $this->product($seller, 'Main product');
        $related = $this->product($seller, 'Related product');

        $this->actingAs($this->user('buyer', 'customer'))
            ->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('Related product')
            ->assertSee('action="'.route('questions.store', $product).'"', false)
            ->assertDontSee('action="'.route('questions.store', $related).'"', false);
    }

    public function test_seller_orders_only_offer_shipping_for_shippable_items(): void
    {
        $seller = $this->user('seller', 'seller');
        $buyer = $this->user('buyer', 'customer');
        $product = $this->product($seller, 'Shippable');
        $paid = $this->orderItem($buyer, $seller, $product, 'PAID-1', 'paid', 'processing');
        $unpaid = $this->orderItem($buyer, $seller, $product, 'UNPAID-1', 'unpaid', 'pending');

        $this->actingAs($seller)->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee(route('seller.orders.items.ship', [$paid->order, $paid]), false)
            ->assertDontSee(route('seller.orders.items.ship', [$unpaid->order, $unpaid]), false);
    }

    private function user(string $account, string $role): User
    {
        return User::create([
            'name' => $account,
            'account' => $account,
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function product(User $seller, string $name): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'name' => $name,
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
    }

    private function orderItem(User $buyer, User $seller, Product $product, string $number, string $payment, string $fulfillment): OrderItem
    {
        $order = Order::create([
            'number' => $number,
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => $payment,
            'fulfillment_status' => $fulfillment,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);
    }
}
