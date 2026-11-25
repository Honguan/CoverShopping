<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\Payments\EcpayGateway;
use App\Services\Payments\InvalidPaymentNotification;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function pay(Order $order)
    {
        $this->authorize('pay', $order);

        try {
            return match (config('payment.gateway')) {
                'stripe' => app(StripeGateway::class)->checkout($order),
                'ecpay' => app(EcpayGateway::class)->checkout($order),
                default => abort(404),
            };
        } catch (RequestException $exception) {
            Log::error('Payment checkout failed', ['order' => $order->id, 'status' => $exception->response->status()]);

            return redirect()->route('orders.show', $order)->withErrors(['payment' => __('ui.payment_gateway_unavailable')]);
        }
    }

    public function stripeWebhook(Request $request, StripeGateway $gateway, OrderPaymentService $payments)
    {
        try {
            $paid = $gateway->paidSession($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (InvalidPaymentNotification) {
            return response('Invalid signature', 400);
        }

        if ($paid) {
            $payments->confirmGatewayPayment($paid['order_id'], 'stripe', $paid['reference'], $paid['amount']);
        }

        return response('OK');
    }

    /** ECPay expects the literal body "1|OK" once a notification is accepted, otherwise it retries. */
    public function ecpayNotify(Request $request, EcpayGateway $gateway, OrderPaymentService $payments)
    {
        try {
            $paid = $gateway->paidNotification($request->post());
        } catch (InvalidPaymentNotification) {
            return response('0|CheckMacValue Error', 400);
        }

        if ($paid) {
            $payments->confirmGatewayPayment($paid['order_id'], 'ecpay', $paid['reference'], $paid['amount']);
        }

        return response('1|OK');
    }
}
