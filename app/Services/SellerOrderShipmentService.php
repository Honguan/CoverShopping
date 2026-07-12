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
        return DB::transaction(function () use ($seller, $order, $orderItem, $request) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($orderItem->id);
            abort_unless($orderItem->order_id === $order->id, 404);
            $orderItem->setRelation('order', $order);
            Gate::forUser($seller)->authorize('ship', $orderItem);

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
