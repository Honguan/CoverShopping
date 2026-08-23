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
use RuntimeException;

class OrderPaymentService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function transition(User $admin, Order $order, string $status, ?Request $request = null): Order
    {
        abort_unless($admin->isRole('admin'), 403);

        return DB::transaction(function () use ($admin, $order, $status, $request) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);

            if ($order->payment_status === $status) {
                return $order;
            }

            $isPaid = $order->payment_status === 'unpaid'
                && $status === 'paid'
                && $order->fulfillment_status === 'pending';
            $isFailed = $order->payment_status === 'unpaid'
                && $status === 'failed'
                && $order->fulfillment_status === 'pending';
            $isVoided = $order->payment_status === 'paid'
                && $status === 'refunded'
                && in_array($order->fulfillment_status, ['pending', 'processing'], true)
                && $order->return_status === 'none';
            $isReturned = $order->payment_status === 'paid'
                && $status === 'refunded'
                && in_array($order->fulfillment_status, ['shipped', 'completed'], true)
                && $order->return_status === 'received';

            if (! $isPaid && ! $isFailed && ! $isVoided && ! $isReturned) {
                throw new RuntimeException('Invalid payment status transition.');
            }

            if ($isFailed || $isVoided) {
                $this->restoreInventory($admin, $order, $isFailed ? 'payment_failed' : 'payment_refunded');
            }

            $order->update([
                'payment_status' => $status,
                'fulfillment_status' => match (true) {
                    $isPaid => 'processing',
                    $isFailed, $isVoided => 'cancelled',
                    default => $order->fulfillment_status,
                },
            ]);
            $this->auditLogService->writeLog('admin.order.payment_updated', $order, ['payment_status' => $status], $request);

            return $order->refresh();
        }, 3);
    }

    private function restoreInventory(User $admin, Order $order, string $reason): void
    {
        $products = Product::whereKey($order->items->pluck('product_id')->filter()->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $variants = ProductVariant::whereKey($order->items->pluck('product_variant_id')->filter()->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $item) {
            $stock = $item->product_variant_id
                ? $variants->get($item->product_variant_id)
                : $products->get($item->product_id);

            if (! $stock) {
                continue;
            }

            $stock->inventory += $item->quantity;
            $stock->save();

            InventoryMovement::create([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'user_id' => $admin->id,
                'reason' => $reason,
                'quantity_delta' => $item->quantity,
                'inventory_after' => $stock->inventory,
                'reference_type' => OrderItem::class,
                'reference_id' => $item->id,
            ]);
        }
    }
}
