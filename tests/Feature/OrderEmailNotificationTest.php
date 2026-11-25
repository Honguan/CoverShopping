<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderStatusMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_gets_an_email_for_each_order_milestone(): void
    {
        Notification::fake();
        $seller = $this->user('seller', 'seller');
        $admin = $this->user('admin', 'admin');
        $buyer = $this->user('buyer', 'customer', 'buyer@example.test');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Tee', 'price' => 100, 'inventory' => 5, 'status' => 'active']);
        $shipping = ShippingMethod::create(['name' => 'Home', 'fee' => 80, 'is_active' => true]);

        $this->actingAs($buyer)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), ['shipping_method_id' => $shipping->id])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $item = OrderItem::firstOrFail();

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'paid'])->assertSessionHasNoErrors();
        $this->actingAs($seller)->patch(route('seller.orders.items.ship', [$order, $item]))->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('returns.store', $order), ['reason' => 'Too small'])->assertSessionHasNoErrors();
        $returnRequest = ReturnRequest::firstOrFail();
        foreach (['approved', 'received', 'refunded'] as $status) {
            $this->actingAs($admin)->patch(route('admin.returns.status', $returnRequest), ['status' => $status])->assertSessionHasNoErrors();
        }

        $events = [];
        Notification::assertSentTo($buyer, OrderStatusMail::class, function (OrderStatusMail $mail) use (&$events, $order) {
            $events[] = $mail->event;

            return $mail->order->is($order);
        });
        $this->assertSame(['placed', 'paid', 'shipped', 'return_approved', 'return_received', 'refunded'], $events);
        Notification::assertNotSentTo([$seller, $admin], OrderStatusMail::class);
    }

    public function test_cancel_and_failed_payment_send_their_own_email(): void
    {
        Notification::fake();
        $admin = $this->user('admin', 'admin');
        $buyer = $this->user('buyer', 'customer', 'buyer@example.test');
        $cancelled = $this->order($buyer, 'CANCEL-1');
        $failed = $this->order($buyer, 'FAIL-1');

        $this->actingAs($buyer)->post(route('orders.cancel', $cancelled))->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.orders.payment', $failed), ['payment_status' => 'failed'])->assertSessionHasNoErrors();

        Notification::assertSentTo($buyer, OrderStatusMail::class, fn (OrderStatusMail $mail) => $mail->order->is($cancelled) && $mail->event === 'cancelled');
        Notification::assertSentTo($buyer, OrderStatusMail::class, fn (OrderStatusMail $mail) => $mail->order->is($failed) && $mail->event === 'payment_failed');
    }

    public function test_mail_content_links_to_the_order_and_members_without_email_get_none(): void
    {
        $buyer = $this->user('buyer', 'customer', 'buyer@example.test');
        $order = $this->order($buyer, 'MAIL-1');

        $mail = (new OrderStatusMail($order, 'paid'))->toMail($buyer);
        $this->assertSame(__('ui.mail_order_paid_subject', ['number' => 'MAIL-1']), $mail->subject);
        $this->assertSame(route('orders.show', $order), $mail->actionUrl);
        $this->assertContains(__('ui.mail_order_total', ['total' => '180']), $mail->introLines);

        config(['mail.default' => 'array']);
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $noEmail = $this->user('quiet', 'customer');
        $noEmail->notify(new OrderStatusMail($this->order($noEmail, 'MAIL-2'), 'placed'));
        $this->assertCount(0, $transport->messages());

        $buyer->notify(new OrderStatusMail($order, 'placed'));
        $this->assertCount(1, $transport->messages());
        $this->assertSame('buyer@example.test', $transport->messages()->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    private function user(string $account, string $role, ?string $email = null): User
    {
        return User::create(['name' => $account, 'account' => $account, 'email' => $email, 'password' => 'password', 'role' => $role, 'status' => 'active']);
    }

    private function order(User $buyer, string $number): Order
    {
        return Order::create(['number' => $number, 'user_id' => $buyer->id, 'subtotal' => 100, 'shipping_fee' => 80, 'total' => 180, 'payment_status' => 'unpaid', 'fulfillment_status' => 'pending']);
    }
}
