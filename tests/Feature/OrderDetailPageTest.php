<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_the_price_breakdown_items_and_return_history(): void
    {
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);
        ReturnRequest::create(['order_id' => $order->id, 'user_id' => $buyer->id, 'reason' => 'Wrong size', 'status' => 'requested']);

        $this->actingAs($buyer)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('href="'.route('orders.show', $order).'"', false);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSeeInOrder(['DETAIL-1', 'Home Delivery', 'Shirt', 'Size: L', '$150', '$300', 'SAVE50', '-$50', '$80', '$330'])
            ->assertSee('Wrong size')
            ->assertSee(route('orders.cancel', $order), false);
    }

    public function test_other_members_and_guests_cannot_view_the_order(): void
    {
        $order = $this->order($this->user('buyer'));

        $this->get(route('orders.show', $order))->assertRedirect(route('login'));
        $this->actingAs($this->user('other'))->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_paid_order_hides_the_cancel_action(): void
    {
        $buyer = $this->user('buyer');
        $order = $this->order($buyer, 'paid');

        $this->actingAs($buyer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee(route('orders.cancel', $order), false)
            ->assertSee(route('orders.reorder', $order), false);
    }

    private function user(string $account): User
    {
        return User::create([
            'name' => $account,
            'account' => $account,
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    private function order(User $buyer, string $paymentStatus = 'unpaid'): Order
    {
        $order = Order::create([
            'number' => 'DETAIL-1',
            'user_id' => $buyer->id,
            'shipping_method_name' => 'Home Delivery',
            'coupon_code' => 'SAVE50',
            'subtotal' => 300,
            'discount_total' => 50,
            'shipping_fee' => 80,
            'total' => 330,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $paymentStatus === 'paid' ? 'processing' : 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Shirt',
            'variant_name' => 'Size: L',
            'unit_price' => 150,
            'quantity' => 2,
            'subtotal' => 300,
            'shipping_status' => 'pending',
        ]);

        return $order;
    }
}
