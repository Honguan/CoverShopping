<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerProductQuestionRequest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\CreateProductVariantRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Services\AuditLogService;
use App\Services\InventoryAdjustmentService;
use App\Services\SellerOrderShipmentService;
use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function showSellerProducts(Request $request)
    {
        $sellerId = $request->user()->id;
        $lowStockThreshold = (int) config('commerce.low_stock_threshold', 5);

        return view('seller.products', [
            'products' => Product::where('seller_id', $sellerId)
                ->with(['variants', 'questions.answers'])
                ->latest()
                ->paginate(20),
            'lowStockProducts' => Product::where('seller_id', $sellerId)
                ->where('inventory', '<=', $lowStockThreshold)
                ->orderBy('inventory')
                ->limit(10)
                ->get(),
            'questions' => ProductQuestion::whereHas('product', fn ($query) => $query->where('seller_id', $sellerId))
                ->with(['product', 'user', 'answers.user'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function createProduct(CreateProductRequest $request, AuditLogService $auditLogService, InventoryAdjustmentService $inventoryAdjustmentService)
    {
        $data = $request->validated();
        $images = $request->file('images', []);
        $inventory = $data['inventory'];
        unset($data['images'], $data['inventory']);

        $product = Product::create($data + [
            'seller_id' => $request->user()->id,
            'status' => $request->user()->isRole('admin') ? 'active' : 'pending',
            'business_min_quantity' => $data['business_min_quantity'] ?? 1,
            'inventory' => 0,
        ]);
        $inventoryAdjustmentService->setProductInventory($product, $request->user(), $inventory, 'seller_initial_stock');

        foreach ($images as $index => $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $auditLogService->writeLog('seller.product.created', $product, $data, $request);

        return redirect()->route('seller.products.index')->with('status', 'Product created.');
    }

    public function updateProductInfo(CreateProductRequest $request, Product $product, AuditLogService $auditLogService, InventoryAdjustmentService $inventoryAdjustmentService)
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $images = $request->file('images', []);
        $inventory = $data['inventory'];
        unset($data['images'], $data['inventory']);

        $product->update($data + [
            'status' => $product->status === 'active' ? 'active' : 'pending',
            'business_min_quantity' => $data['business_min_quantity'] ?? 1,
        ]);
        $inventoryAdjustmentService->setProductInventory($product, $request->user(), $inventory, 'seller_adjustment');

        foreach ($images as $index => $image) {
            $product->images()->create([
                'path' => $image->store('products', 'public'),
                'is_primary' => !$product->images()->where('is_primary', true)->exists() && $index === 0,
                'sort_order' => $product->images()->count() + $index,
            ]);
        }

        $auditLogService->writeLog('seller.product.updated', $product, $data, $request);

        return redirect()->route('seller.products.index')->with('status', 'Product updated.');
    }

    public function createProductVariant(CreateProductVariantRequest $request, Product $product, AuditLogService $auditLogService)
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $variant = $product->variants()->create([
            'sku' => $data['sku'],
            'option_name' => $data['option_name'],
            'option_value' => $data['option_value'],
            'price_delta' => $data['price_delta'] ?? 0,
            'inventory' => $data['inventory'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $auditLogService->writeLog('seller.product_variant.created', $variant, $data, $request);

        return redirect()->route('seller.products.index')->with('status', 'Product variant created.');
    }

    public function answerProductQuestion(AnswerProductQuestionRequest $request, ProductQuestion $productQuestion, AuditLogService $auditLogService)
    {
        $productQuestion->load('product');
        $this->authorize('answer', $productQuestion);

        $data = $request->validated();
        $answer = $productQuestion->answers()->create([
            'user_id' => $request->user()->id,
            'answer' => $data['answer'],
        ]);
        $productQuestion->update(['status' => 'answered']);

        Notification::create([
            'user_id' => $productQuestion->user_id,
            'type' => 'product_question_answered',
            'title' => 'Your product question was answered.',
            'body' => $productQuestion->product->name,
            'url' => route('catalog.show', $productQuestion->product),
        ]);

        $auditLogService->writeLog('seller.question.answered', $answer, ['question' => $productQuestion->id], $request);

        return redirect()->route('seller.products.index')->with('status', 'Question answered.');
    }

    public function showSellerOrders(Request $request)
    {
        return view('seller.orders', [
            'items' => OrderItem::where('seller_id', $request->user()->id)
                ->with(['order.user', 'product'])
                ->latest()
                ->paginate(30),
        ]);
    }

    public function markOrderItemShipped(Request $request, Order $order, OrderItem $orderItem, SellerOrderShipmentService $shipments)
    {
        $shipments->markItemShipped($request->user(), $order, $orderItem, $request);

        return redirect()->route('seller.orders.index')->with('status', 'Order item shipped.');
    }
}
