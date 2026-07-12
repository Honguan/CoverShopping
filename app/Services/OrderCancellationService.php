<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class OrderCancellationService
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function cancel(User $user, Order $order, ?Request $request = null): Order
    {
        $order->refresh();
        Gate::forUser($user)->authorize('cancel', $order);

        return DB::transaction(function () use ($order, $request) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);

            if ($order->payment_status !== 'unpaid' || $order->fulfillment_status !== 'pending') {
                throw new RuntimeException(__('ui.order_cannot_cancel'));
            }

            $products = Product::whereKey($order->items->pluck('product_id')->filter()->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $variants = ProductVariant::whereKey($order->items->pluck('product_variant_id')->filter()->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    $variant = $variants->get($item->product_variant_id);
                    if (! $variant) {
                        continue;
                    }

                    $variant->inventory += $item->quantity;
                    $variant->save();
                    $inventoryAfter = $variant->inventory;
                } else {
                    $product = $products->get($item->product_id);
                    if (! $product) {
                        continue;
                    }

                    $product->inventory += $item->quantity;
                    $product->save();
                    $inventoryAfter = $product->inventory;
                }

                InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'user_id' => $order->user_id,
                    'reason' => 'order_cancelled',
                    'quantity_delta' => $item->quantity,
                    'inventory_after' => $inventoryAfter,
                    'reference_type' => OrderItem::class,
                    'reference_id' => $item->id,
                ]);
            }

            $order->update(['fulfillment_status' => 'cancelled']);
            $this->auditLogService->writeLog('order.cancelled', $order, ['number' => $order->number], $request);

            return $order->refresh();
        }, 3);
    }
}
