<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\ProductPricingService;
use App\Services\PromotionService;
use App\Services\ShoppingCartService;
use Illuminate\Http\Request;

class ShoppingCartController extends Controller
{
    public function showCart(Request $request, ShoppingCartService $shoppingCartService, ProductPricingService $productPricingService, PromotionService $promotionService)
    {
        $user = $request->user();
        $user?->loadMissing('businessProfile');
        $items = $shoppingCartService->getItemsForUserOrSession($user, $request->session()->getId());
        $shippingMethods = ShippingMethod::where('is_active', true)->orderBy('sort_order')->get();
        $addresses = $user ? $user->addresses()->latest()->get() : collect();

        return view('cart.index', [
            'items' => $items,
            'itemStatusMessages' => $shoppingCartService->statusMessagesForItems($items, $user),
            'subtotal' => $items->sum(fn (CartItem $item) => $productPricingService->calculateUnitPrice($item->product, $item->variant, $user, $item->quantity) * $item->quantity),
            'shippingMethods' => $shippingMethods,
            'defaultShippingMethod' => $shippingMethods->first(),
            'addresses' => $addresses,
            'defaultAddress' => $addresses->firstWhere('is_default', true),
            'promotionService' => $promotionService,
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

        $addedQuantity = $shoppingCartService->addAvailableQuantity($request->user(), $request->session()->getId(), $product, $data['quantity'], $variant);

        if ($addedQuantity < 1) {
            return back()->withErrors(['product_id' => 'Product is out of stock.']);
        }

        return redirect()->route('cart.index')->with('status', 'Added ' . $addedQuantity . ' item(s) to cart.');
    }

    public function changeItemQuantity(UpdateCartItemRequest $request, CartItem $cartItem, ShoppingCartService $shoppingCartService)
    {
        $this->authorize('manage', $cartItem);

        $shoppingCartService->updateQuantity($cartItem->load(['product', 'variant']), $request->validated('quantity'));

        return redirect()->route('cart.index')->with('status', 'Cart updated.');
    }

    public function clearItems(Request $request, ShoppingCartService $shoppingCartService)
    {
        $shoppingCartService->clearItemsForUserOrSession($request->user(), $request->session()->getId());

        return redirect()->route('cart.index')->with('status', 'Cart cleared.');
    }

    public function removeItem(CartItem $cartItem)
    {
        $this->authorize('manage', $cartItem);
        $cartItem->delete();

        return redirect()->route('cart.index')->with('status', 'Item removed.');
    }
}
