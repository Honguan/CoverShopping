<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\ProductPricingService;
use App\Services\ShoppingCartService;
use Illuminate\Http\Request;

class ShoppingCartController extends Controller
{
    public function showCart(Request $request, ShoppingCartService $shoppingCartService, ProductPricingService $productPricingService)
    {
        $user = $request->user();
        $user?->loadMissing('businessProfile');
        $items = $shoppingCartService->getItemsForUserOrSession($user, $request->session()->getId());

        return view('cart.index', [
            'items' => $items,
            'subtotal' => $items->sum(fn (CartItem $item) => $productPricingService->calculateUnitPrice($item->product, $item->variant, $user, $item->quantity) * $item->quantity),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('sort_order')->get(),
            'pricingService' => $productPricingService,
        ]);
    }

    public function addItem(AddCartItemRequest $request, ShoppingCartService $shoppingCartService)
    {
        $data = $request->validated();
        $product = Product::active()->findOrFail($data['product_id']);
        $variant = null;

        if (!empty($data['product_variant_id'])) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('is_active', true)
                ->findOrFail($data['product_variant_id']);
        }

        $availableInventory = $variant ? $variant->inventory : $product->inventory;
        if ($availableInventory < 1) {
            return back()->withErrors(['product_id' => '商品已售完']);
        }

        $shoppingCartService->addProduct($request->user(), $request->session()->getId(), $product, $data['quantity'], $variant);

        return redirect()->route('cart.index')->with('status', '已加入購物車');
    }

    public function changeItemQuantity(UpdateCartItemRequest $request, CartItem $cartItem, ShoppingCartService $shoppingCartService)
    {
        $this->authorize('manage', $cartItem);

        $shoppingCartService->updateQuantity($cartItem->load(['product', 'variant']), $request->validated('quantity'));

        return redirect()->route('cart.index');
    }

    public function removeItem(CartItem $cartItem)
    {
        $this->authorize('manage', $cartItem);
        $cartItem->delete();

        return redirect()->route('cart.index');
    }
}
