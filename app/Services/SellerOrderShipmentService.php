<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SellerOrderShipmentService
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function markItemShipped(User $seller, Order $order, OrderItem $orderItem, ?Request $request = null): OrderItem
    {
        Gate::forUser($seller)->authorize('ship', $orderItem);
        abort_unless($orderItem->order_id === $order->id, 404);

        return DB::transaction(function () use ($order, $orderItem, $request) {
            $orderItem->update([
                'shipping_status' => 'shipped',
                'shipped_at' => now(),
            ]);

            $statuses = $order->items()->pluck('shipping_status');
            $order->update([
                'fulfillment_status' => $statuses->every(fn ($status) => $status === 'shipped') ? 'completed' : 'partially_shipped',
            ]);

            $this->auditLogService->writeLog('seller.order_item.shipped', $orderItem, ['order' => $order->number], $request);

            return $orderItem->refresh();
        });
    }
}
