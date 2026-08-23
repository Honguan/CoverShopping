<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ReturnRequestService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private OrderPaymentService $orderPaymentService,
    ) {}

    public function request(User $user, Order $order, string $reason, ?Request $request = null): ReturnRequest
    {
        return DB::transaction(function () use ($user, $order, $reason, $request) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($user)->authorize('requestReturn', $order);

            $returnRequest = $order->returnRequests()->create([
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => 'requested',
            ]);

            $order->update(['return_status' => 'requested']);
            $this->auditLogService->writeLog('return.requested', $returnRequest, ['order' => $order->number], $request);

            return $returnRequest;
        });
    }

    public function updateStatus(User $admin, ReturnRequest $returnRequest, string $status, ?Request $request = null): ReturnRequest
    {
        abort_unless($admin->isRole('admin'), 403);

        return DB::transaction(function () use ($admin, $returnRequest, $status, $request) {
            $returnRequest = ReturnRequest::query()->lockForUpdate()->findOrFail($returnRequest->id);

            if ($returnRequest->status === $status) {
                return $returnRequest;
            }

            if (! $this->canTransition($returnRequest->status, $status)) {
                throw new RuntimeException('Invalid return status transition.');
            }

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($returnRequest->order_id);

            if ($status === 'refunded') {
                $this->orderPaymentService->transition($admin, $order, 'refunded', $request);
            }

            $returnUpdates = ['status' => $status];

            if ($status === 'received' && ! $returnRequest->inventory_restocked_at) {
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
                        'user_id' => $admin->id,
                        'reason' => 'return_received',
                        'quantity_delta' => $item->quantity,
                        'inventory_after' => $inventoryAfter,
                        'reference_type' => OrderItem::class,
                        'reference_id' => $item->id,
                    ]);
                }

                $returnUpdates['inventory_restocked_at'] = now();
            }

            $returnRequest->update($returnUpdates);
            $order->update(['return_status' => $status]);
            $this->auditLogService->writeLog('admin.return.updated', $returnRequest, ['status' => $status], $request);

            return $returnRequest->refresh();
        }, 3);
    }

    private function canTransition(string $from, string $to): bool
    {
        return match ($from) {
            'requested' => in_array($to, ['approved', 'rejected'], true),
            'approved' => in_array($to, ['received', 'rejected'], true),
            'received' => $to === 'refunded',
            default => false,
        };
    }
}
