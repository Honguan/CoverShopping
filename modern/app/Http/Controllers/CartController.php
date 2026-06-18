<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService)
    {
        $items = $cartService->items($request->user(), $request->session()->getId());

        return view('cart.index', [
            'items' => $items,
            'subtotal' => $items->sum(fn (CartItem $item) => $item->product->price * $item->quantity),
        ]);
    }

    public function store(Request $request, CartService $cartService)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        if ($product->inventory < 1) {
            return back()->withErrors(['product_id' => '商品已售完']);
        }

        $cartService->add($request->user(), $request->session()->getId(), $product, $data['quantity']);

        return redirect()->route('cart.index')->with('status', '已加入購物車');
    }

    public function update(Request $request, CartItem $cartItem, CartService $cartService)
    {
        $this->authorizeCartItem($request, $cartItem);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartService->update($cartItem->load('product'), $data['quantity']);

        return redirect()->route('cart.index');
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);
        $cartItem->delete();

        return redirect()->route('cart.index');
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        $ownsItem = $request->user()
            ? $cartItem->user_id === $request->user()->id
            : $cartItem->session_id === $request->session()->getId();

        abort_unless($ownsItem, 403);
    }
}
