<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (cards, Apple Pay, Google Pay and other methods enabled in the Stripe dashboard). */
class StripeGateway
{
    private const ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    private const SIGNATURE_TOLERANCE = 300;

    public function checkout(Order $order): RedirectResponse
    {
        $session = Http::asForm()
            ->withToken((string) config('payment.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', array_filter([
                'mode' => 'payment',
                'client_reference_id' => (string) $order->id,
                'metadata' => ['order_number' => $order->number],
                'customer_email' => $order->user?->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => config('payment.stripe.currency'),
                        'unit_amount' => $this->minorAmount($order->total),
                        'product_data' => ['name' => config('app.name').' #'.$order->number],
                    ],
                ]],
                'success_url' => route('orders.show', $order),
                'cancel_url' => route('orders.show', $order),
            ]))
            ->throw();

        return redirect()->away((string) $session->json('url'));
    }

    /**
     * Verifies the Stripe-Signature header and returns the paid order id, reference and amount,
     * or null for events that do not confirm a payment.
     *
     * @return array{order_id: int, reference: string, amount: int|null}|null
     */
    public function paidSession(string $payload, string $signatureHeader): ?array
    {
        if (! $this->validSignature($payload, $signatureHeader)) {
            throw new InvalidPaymentNotification('Invalid Stripe signature.');
        }

        $event = json_decode($payload, true);
        $session = $event['data']['object'] ?? [];
        $confirms = in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($session['payment_status'] ?? '') === 'paid';

        if (! $confirms || ! ctype_digit((string) ($session['client_reference_id'] ?? ''))) {
            return null;
        }

        $currencyMatches = strtolower((string) ($session['currency'] ?? '')) === config('payment.stripe.currency');

        return [
            'order_id' => (int) $session['client_reference_id'],
            'reference' => (string) ($session['payment_intent'] ?? $session['id'] ?? ''),
            'amount' => $currencyMatches ? $this->majorAmount((int) ($session['amount_total'] ?? -1)) : null,
        ];
    }

    private function validSignature(string $payload, string $header): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $secret = (string) config('payment.stripe.webhook_secret');

        if ($secret === '' || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function minorAmount(int $amount): int
    {
        return in_array(config('payment.stripe.currency'), self::ZERO_DECIMAL, true) ? $amount : $amount * 100;
    }

    /** Converts back to whole currency units; a fractional amount cannot match an order total. */
    private function majorAmount(int $minor): ?int
    {
        if (in_array(config('payment.stripe.currency'), self::ZERO_DECIMAL, true)) {
            return $minor;
        }

        return $minor % 100 === 0 ? intdiv($minor, 100) : null;
    }
}
