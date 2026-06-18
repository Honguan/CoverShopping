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

        $added = 0;
        $order->load('items.product', 'items.variant');

        foreach ($order->items as $item) {
            if (!$item->product || $item->product->status !== 'active') {
                continue;
            }

            $shoppingCartService->addProduct(
                $request->user(),
                $request->session()->getId(),
                $item->product,
                $item->quantity,
                $item->variant
            );
            $added++;
        }

        return redirect()->route('cart.index')->with('status', $added > 0 ? '已加入購物車' : '訂單商品目前無法再次購買');
    }
}
