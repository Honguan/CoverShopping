<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusMail;
use App\Services\Payments\EcpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const ECPAY = ['merchant_id' => '3002607', 'hash_key' => 'pwFHCqoQZGmho4w6', 'hash_iv' => 'EkRm7iFT261dpevs', 'mode' => 'stage', 'endpoint' => 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'];

    public function test_ecpay_check_mac_value_matches_the_published_example(): void
    {
        config(['payment.ecpay' => self::ECPAY]);

        // Example from the ECPay AIO developer guide (CheckMacValue section).
        $this->assertSame('6C51C9E6888DE861FD62FB1DD17029FC742634498FD813DC43D4243B5685B840', app(EcpayGateway::class)->checkMacValue([
            'ChoosePayment' => 'ALL',
            'EncryptType' => '1',
            'ItemName' => 'Apple iphone 15',
            'MerchantID' => '3002607',
            'MerchantTradeDate' => '2023/03/12 15:30:23',
            'MerchantTradeNo' => 'ecpay20230312153023',
            'PaymentType' => 'aio',
            'ReturnURL' => 'https://www.ecpay.com.tw/receive.php',
            'TotalAmount' => '30000',
            'TradeDesc' => '促銷方案',
        ]));
    }

    public function test_pay_button_only_shows_for_the_owner_of_an_unpaid_order_with_a_gateway(): void
    {
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);

        $this->actingAs($buyer)->get(route('orders.show', $order))->assertDontSee(route('orders.pay', $order), false);
        $this->post(route('orders.pay', $order))->assertForbidden();

        $this->useEcpay();
        $this->get(route('orders.show', $order))->assertSee(route('orders.pay', $order), false);
        $this->actingAs($this->user('other'))->post(route('orders.pay', $order))->assertForbidden();
        $order->update(['payment_status' => 'paid', 'fulfillment_status' => 'processing']);
        $this->actingAs($buyer)->post(route('orders.pay', $order))->assertForbidden();
    }

    public function test_ecpay_checkout_posts_signed_fields_to_the_gateway(): void
    {
        $this->useEcpay();
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);

        $response = $this->actingAs($buyer)->post(route('orders.pay', $order))->assertOk();
        $fields = $response->viewData('fields');

        $response->assertSee('action="'.self::ECPAY['endpoint'].'"', false);
        $this->assertSame(['330', (string) $order->id, route('payments.ecpay.notify')], [$fields['TotalAmount'], $fields['CustomField1'], $fields['ReturnURL']]);
        $this->assertSame(app(EcpayGateway::class)->checkMacValue($fields), $fields['CheckMacValue']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{20}$/', $fields['MerchantTradeNo']);
        $this->assertStringContainsString('form-action \'self\' https://payment-stage.ecpay.com.tw', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_ecpay_notification_marks_the_order_paid_once(): void
    {
        Notification::fake();
        $this->useEcpay();
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);
        $notice = $this->signedEcpay(['CustomField1' => (string) $order->id, 'TradeAmt' => '330', 'TradeNo' => '2311251234567890']);

        $this->post(route('payments.ecpay.notify'), $notice)->assertOk()->assertSeeText('1|OK');
        $this->post(route('payments.ecpay.notify'), $notice)->assertOk()->assertSeeText('1|OK');

        $order->refresh();
        $this->assertSame(['paid', 'processing', 'ecpay', '2311251234567890'], [$order->payment_status, $order->fulfillment_status, $order->payment_gateway, $order->payment_reference]);
        $this->assertDatabaseCount('audit_logs', 1);
        Notification::assertSentToTimes($buyer, OrderStatusMail::class, 1);
    }

    public function test_ecpay_rejects_tampered_wrong_amount_unpaid_and_simulated_production_notices(): void
    {
        $this->useEcpay();
        $order = $this->order($this->user('buyer'));
        $base = ['CustomField1' => (string) $order->id, 'TradeAmt' => '330', 'TradeNo' => 'T1'];

        $tampered = $this->signedEcpay($base);
        $tampered['TradeAmt'] = '1';
        $this->post(route('payments.ecpay.notify'), $tampered)->assertStatus(400);

        $this->post(route('payments.ecpay.notify'), $this->signedEcpay(['RtnCode' => '2'] + $base))->assertSeeText('1|OK');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);

        $this->post(route('payments.ecpay.notify'), $this->signedEcpay(['TradeAmt' => '100'] + $base))->assertSeeText('1|OK');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.gateway.unmatched', 'auditable_id' => $order->id]);

        config(['payment.ecpay.mode' => 'production']);
        $this->post(route('payments.ecpay.notify'), $this->signedEcpay(['SimulatePaid' => '1'] + $base))->assertSeeText('1|OK');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);
    }

    public function test_stripe_checkout_creates_a_session_and_redirects(): void
    {
        $this->useStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);

        $this->actingAs($buyer)->post(route('orders.pay', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->hasHeader('Authorization', 'Bearer sk_test_x')
            && $request['client_reference_id'] === (string) $order->id
            && $request['line_items'][0]['price_data']['unit_amount'] === 33000
            && $request['line_items'][0]['price_data']['currency'] === 'twd');
    }

    public function test_stripe_outage_returns_to_the_order_with_an_error(): void
    {
        $this->useStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);
        $buyer = $this->user('buyer');
        $order = $this->order($buyer);

        $this->actingAs($buyer)->post(route('orders.pay', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('payment');
    }

    public function test_stripe_webhook_requires_a_fresh_valid_signature(): void
    {
        Notification::fake();
        $this->useStripe();
        $order = $this->order($this->user('buyer'));
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_1', 'client_reference_id' => (string) $order->id, 'payment_status' => 'paid',
            'payment_intent' => 'pi_123', 'amount_total' => 33000, 'currency' => 'twd',
        ]]]);

        $this->stripeWebhook($payload, 'whsec_wrong')->assertStatus(400);
        $this->stripeWebhook($payload, 'whsec_test', time() - 3600)->assertStatus(400);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);

        $this->stripeWebhook($payload, 'whsec_test')->assertOk();
        $this->assertSame(['paid', 'stripe', 'pi_123'], [$order->fresh()->payment_status, $order->fresh()->payment_gateway, $order->fresh()->payment_reference]);
    }

    private function stripeWebhook(string $payload, string $secret, ?int $timestamp = null)
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', route('payments.stripe.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    /** @param array<string, string> $overrides */
    private function signedEcpay(array $overrides): array
    {
        $data = $overrides + [
            'MerchantID' => self::ECPAY['merchant_id'], 'MerchantTradeNo' => 'ABCDEFGHIJ0123456789', 'RtnCode' => '1', 'RtnMsg' => '交易成功',
            'PaymentDate' => '2026/11/25 10:00:00', 'PaymentType' => 'Credit_CreditCard', 'SimulatePaid' => '0', 'TradeDate' => '2026/11/25 09:59:00',
        ];
        $data['CheckMacValue'] = app(EcpayGateway::class)->checkMacValue($data);

        return $data;
    }

    private function useEcpay(): void
    {
        config(['payment.gateway' => 'ecpay', 'payment.checkout_origin' => 'https://payment-stage.ecpay.com.tw', 'payment.ecpay' => self::ECPAY]);
    }

    private function useStripe(): void
    {
        config(['payment.gateway' => 'stripe', 'payment.checkout_origin' => 'https://checkout.stripe.com', 'payment.stripe' => ['secret' => 'sk_test_x', 'webhook_secret' => 'whsec_test', 'currency' => 'twd']]);
    }

    private function user(string $account): User
    {
        return User::create(['name' => $account, 'account' => $account, 'email' => $account.'@example.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
    }

    private function order(User $buyer): Order
    {
        return Order::create(['number' => 'PAY-'.$buyer->id, 'user_id' => $buyer->id, 'subtotal' => 250, 'shipping_fee' => 80, 'total' => 330, 'payment_status' => 'unpaid', 'fulfillment_status' => 'pending']);
    }
}
