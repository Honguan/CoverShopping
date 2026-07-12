<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutCartRequest;
use App\Services\AuditLogService;
use App\Services\OrderCheckoutService;
use RuntimeException;

class CheckoutController extends Controller
{
    public function createOrderFromCart(CheckoutCartRequest $request, OrderCheckoutService $orderCheckoutService, AuditLogService $auditLogService)
    {
        $data = $request->validated();

        try {
            $order = $orderCheckoutService->createOrderFromCart(
                $request->user(),
                $data['shipping_method_id'] ?? null,
                $data['address_id'] ?? null,
                $data['coupon_code'] ?? null,
                $data['purchase_order_number'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['checkout' => $exception->getMessage()]);
        }

        $auditLogService->writeLog('order.created', $order, ['number' => $order->number], $request);

        return redirect()->route('orders.index')->with('status', 'Order created.');
    }
}
