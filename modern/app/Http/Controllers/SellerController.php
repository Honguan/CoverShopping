<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function products(Request $request)
    {
        return view('seller.products', [
            'products' => Product::where('seller_id', $request->user()->id)->latest()->paginate(20),
        ]);
    }

    public function storeProduct(Request $request, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'inventory' => ['required', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $images = $request->file('images', []);
        unset($data['images']);

        $product = Product::create($data + [
            'seller_id' => $request->user()->id,
            'status' => $request->user()->isRole('admin') ? 'active' : 'pending',
        ]);

        foreach ($images as $index => $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $auditLogger->log('seller.product.created', $product, $data, $request);

        return redirect()->route('seller.products.index')->with('status', '商品已建立');
    }

    public function updateProduct(Request $request, Product $product, AuditLogger $auditLogger)
    {
        abort_unless($request->user()->isRole('admin') || $product->seller_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'inventory' => ['required', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $images = $request->file('images', []);
        unset($data['images']);

        $product->update($data + [
            'status' => $product->status === 'active' ? 'active' : 'pending',
        ]);

        foreach ($images as $index => $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => !$product->images()->where('is_primary', true)->exists() && $index === 0,
                'sort_order' => $product->images()->count() + $index,
            ]);
        }

        $auditLogger->log('seller.product.updated', $product, $data, $request);

        return redirect()->route('seller.products.index')->with('status', '商品已更新');
    }

    public function orders(Request $request)
    {
        return view('seller.orders', [
            'items' => OrderItem::where('seller_id', $request->user()->id)
                ->with(['order.user', 'product'])
                ->latest()
                ->paginate(30),
        ]);
    }

    public function shipItem(Request $request, Order $order, OrderItem $orderItem, AuditLogger $auditLogger)
    {
        abort_unless($orderItem->order_id === $order->id, 404);
        abort_unless($request->user()->isRole('admin') || $orderItem->seller_id === $request->user()->id, 403);

        $orderItem->update([
            'shipping_status' => 'shipped',
            'shipped_at' => now(),
        ]);

        $statuses = $order->items()->pluck('shipping_status');
        $order->update([
            'fulfillment_status' => $statuses->every(fn ($status) => $status === 'shipped') ? 'completed' : 'partially_shipped',
        ]);

        $auditLogger->log('seller.order_item.shipped', $orderItem, ['order' => $order->number], $request);

        return redirect()->route('seller.orders.index')->with('status', '已標記出貨');
    }
}
