<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use RuntimeException;

class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkoutService, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'shipping_fee' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
        ]);

        try {
            $order = $checkoutService->createOrder(
                $request->user(),
                (int) ($data['shipping_fee'] ?? 0),
                $data['address_id'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['checkout' => $exception->getMessage()]);
        }

        $auditLogger->log('order.created', $order, ['number' => $order->number], $request);

        return redirect()->route('orders.index')->with('status', '訂單已建立');
    }
}
