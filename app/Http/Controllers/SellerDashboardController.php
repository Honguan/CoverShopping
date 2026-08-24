<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerProductQuestionRequest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\CreateProductVariantRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductQuestion;
use App\Services\AuditLogService;
use App\Services\InventoryAdjustmentService;
use App\Services\SellerOrderShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SellerDashboardController extends Controller
{
    public function showSellerProducts(Request $request)
    {
        $sellerId = $request->user()->id;
        $lowStockThreshold = (int) config('commerce.low_stock_threshold', 5);

        return view('seller.products', [
            'products' => Product::where('seller_id', $sellerId)
                ->with(['images', 'variants', 'questions.answers'])
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

        $storedPaths = $this->storeProductImages($images);

        try {
            DB::transaction(function () use ($request, $data, $inventory, $storedPaths, $inventoryAdjustmentService, $auditLogService): void {
                $product = Product::create($data + [
                    'seller_id' => $request->user()->id,
                    'status' => $request->user()->isRole('admin') ? 'active' : 'pending',
                    'business_min_quantity' => $data['business_min_quantity'] ?? 1,
                    'inventory' => 0,
                ]);
                $inventoryAdjustmentService->setProductInventory($product, $request->user(), $inventory, 'seller_initial_stock');

                foreach ($storedPaths as $index => $path) {
                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }

                $auditLogService->writeLog('seller.product.created', $product, $data, $request);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('seller.products.index')->with('status', __('ui.product_created'));
    }

    public function updateProductInfo(CreateProductRequest $request, Product $product, AuditLogService $auditLogService, InventoryAdjustmentService $inventoryAdjustmentService)
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $images = $request->file('images', []);
        $inventory = $data['inventory'];
        unset($data['images'], $data['inventory']);

        $storedPaths = $this->storeProductImages($images);

        try {
            DB::transaction(function () use ($request, $product, $data, $inventory, $storedPaths, $inventoryAdjustmentService, $auditLogService): void {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);

                if ($product->images()->count() + count($storedPaths) > 8) {
                    throw ValidationException::withMessages(['images' => __('ui.product_image_limit')]);
                }

                $product->update($data + [
                    'status' => $product->status === 'active' ? 'active' : 'pending',
                    'business_min_quantity' => $data['business_min_quantity'] ?? 1,
                ]);
                $inventoryAdjustmentService->setProductInventory($product, $request->user(), $inventory, 'seller_adjustment');

                $hasPrimaryImage = $product->images()->where('is_primary', true)->exists();
                $nextSortOrder = ($product->images()->max('sort_order') ?? -1) + 1;

                foreach ($storedPaths as $index => $path) {
                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => ! $hasPrimaryImage && $index === 0,
                        'sort_order' => $nextSortOrder + $index,
                    ]);
                }

                $auditLogService->writeLog('seller.product.updated', $product, $data, $request);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('seller.products.index')->with('status', __('ui.product_updated'));
    }

    public function deleteProductImage(Request $request, Product $product, ProductImage $productImage, AuditLogService $auditLogService)
    {
        $this->authorize('update', $product);
        abort_unless($productImage->product_id === $product->id, 404);

        $path = DB::transaction(function () use ($request, $product, $productImage, $auditLogService): string {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $productImage = ProductImage::query()->lockForUpdate()->findOrFail($productImage->id);
            abort_unless($productImage->product_id === $product->id, 404);

            $wasPrimary = $productImage->is_primary;
            $path = $productImage->path;
            $productImage->delete();

            if ($wasPrimary) {
                $product->images()->first()?->update(['is_primary' => true]);
            }

            $auditLogService->writeLog('seller.product_image.deleted', $productImage, ['product' => $product->id], $request);

            return $path;
        }, 3);

        Storage::disk('public')->delete($path);

        return redirect()->route('seller.products.index')->with('status', __('ui.product_image_removed'));
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

        return redirect()->route('seller.products.index')->with('status', __('ui.product_variant_created'));
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
            'title' => 'ui.notification_product_question_answered',
            'body' => $productQuestion->product->name,
            'url' => route('catalog.show', $productQuestion->product),
        ]);

        $auditLogService->writeLog('seller.question.answered', $answer, ['question' => $productQuestion->id], $request);

        return redirect()->route('seller.products.index')->with('status', __('ui.question_answered'));
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

    public function exportSellerOrders(Request $request)
    {
        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $request->user()->id)
            ->orderByDesc('order_items.id')
            ->select([
                'order_items.product_name',
                'order_items.variant_name',
                'order_items.quantity',
                'order_items.subtotal',
                'order_items.shipping_status',
                'orders.number as order_number',
                'orders.sales_channel',
                'orders.purchase_order_number',
                'orders.payment_status',
            ])
            ->cursor();

        return response()->streamDownload(function () use ($items) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Order Number', 'Channel', 'Purchase Order Number', 'Product', 'Variant', 'Quantity', 'Subtotal', 'Payment Status', 'Shipping Status']);

            foreach ($items as $item) {
                fputcsv($output, [
                    $this->csvValue($item->order_number),
                    $this->csvValue($item->sales_channel),
                    $this->csvValue($item->purchase_order_number),
                    $this->csvValue($item->product_name),
                    $this->csvValue($item->variant_name),
                    $item->quantity,
                    $item->subtotal,
                    $this->csvValue($item->payment_status),
                    $this->csvValue($item->shipping_status),
                ]);
            }

            fclose($output);
        }, 'seller-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^\s*[=+\-@]/', $value) ? "'{$value}" : $value;
    }

    public function markOrderItemShipped(Request $request, Order $order, OrderItem $orderItem, SellerOrderShipmentService $shipments)
    {
        $shipments->markItemShipped($request->user(), $order, $orderItem, $request);

        return redirect()->route('seller.orders.index')->with('status', __('ui.order_item_shipped'));
    }

    private function storeProductImages(array $images): array
    {
        $paths = [];

        try {
            foreach ($images as $image) {
                $path = $image->store('products', 'public');

                if (! is_string($path)) {
                    throw new RuntimeException(__('ui.product_image_storage_failed'));
                }

                $paths[] = $path;
            }

            return $paths;
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);

            throw $exception;
        }
    }
}
