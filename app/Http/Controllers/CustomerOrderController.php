<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ShoppingCartService;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function showCustomerOrders(Request $request)
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()->with('items')->latest()->paginate(20),
        ]);
    }

    public function reorder(Request $request, Order $order, ShoppingCartService $shoppingCartService)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $addedQuantity = 0;
        $skippedItems = 0;
        $order->load('items.product', 'items.variant');

        foreach ($order->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (!$product || $product->status !== 'active') {
                $skippedItems++;
                continue;
            }

            if ($item->product_variant_id && (!$variant || !$variant->is_active || $variant->product_id !== $product->id)) {
                $skippedItems++;
                continue;
            }

            $added = $shoppingCartService->addAvailableQuantity(
                $request->user(),
                $request->session()->getId(),
                $product,
                $item->quantity,
                $variant
            );

            if ($added < 1) {
                $skippedItems++;
                continue;
            }

            $addedQuantity += $added;
        }

        return redirect()
            ->route('cart.index')
            ->with('status', 'Added ' . $addedQuantity . ' item(s), skipped ' . $skippedItems . ' item(s).');
    }
}
