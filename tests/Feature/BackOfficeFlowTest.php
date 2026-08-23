<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackOfficeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_payment_cancels_the_order_and_restores_product_and_variant_inventory_once(): void
    {
        [$seller, $buyer, $admin] = $this->createUsers();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Failed Payment Product',
            'price' => 100,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'FAILED-PAYMENT-SKU',
            'option_name' => 'Color',
            'option_value' => 'Blue',
            'inventory' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'number' => 'PAYMENT-FAILED-1',
            'user_id' => $buyer->id,
            'subtotal' => 300,
            'total' => 300,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'variant_name' => $variant->displayName(),
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $payments = app(OrderPaymentService::class);
        $payments->transition($admin, $order, 'failed');
        $payments->transition($admin, $order, 'failed');

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertSame(1, $product->fresh()->inventory);
        $this->assertSame(2, $variant->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', ['reason' => 'payment_failed']);
    }

    public function test_payment_service_rejects_a_competing_stale_transition_without_changing_inventory(): void
    {
        [$seller, $buyer, $admin] = $this->createUsers();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Paid Product',
            'price' => 100,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'PAYMENT-PAID-1',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'total' => 100,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $payments = app(OrderPaymentService::class);
        $payments->transition($admin, $order, 'paid');

        try {
            $payments->transition($admin, $order, 'failed');
            $this->fail('Expected an invalid payment transition exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid payment status transition.', $exception->getMessage());
        }

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->fulfillment_status);
        $this->assertSame(0, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_refunding_an_unshipped_paid_order_restores_inventory_once(): void
    {
        [$seller, $buyer, $admin] = $this->createUsers();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Voided Product',
            'price' => 100,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'PAYMENT-VOID-1',
            'user_id' => $buyer->id,
            'subtotal' => 200,
            'total' => 200,
            'payment_status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $payments = app(OrderPaymentService::class);
        $payments->transition($admin, $order, 'refunded');
        $payments->transition($admin, $order, 'refunded');

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['reason' => 'payment_refunded']);
    }

    public function test_seller_can_manage_products_questions_and_ship_own_order_items(): void
    {
        [$seller, $buyer] = $this->createUsers();

        $this->actingAs($seller)->post('/seller/products', [
            'name' => 'Seller Product',
            'description' => 'Product description',
            'price' => 500,
            'inventory' => 4,
        ])->assertRedirect('/seller/products');

        $product = Product::where('name', 'Seller Product')->firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $seller->id,
            'reason' => 'seller_initial_stock',
            'quantity_delta' => 4,
            'inventory_after' => 4,
        ]);

        $this->actingAs($seller)->patch("/seller/products/{$product->id}", [
            'name' => 'Seller Product',
            'description' => 'Product description',
            'price' => 500,
            'inventory' => 7,
        ])->assertRedirect('/seller/products');

        $this->assertSame(7, $product->fresh()->inventory);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $seller->id,
            'reason' => 'seller_adjustment',
            'quantity_delta' => 3,
            'inventory_after' => 7,
        ]);

        $this->actingAs($seller)->post("/seller/products/{$product->id}/variants", [
            'sku' => 'SELLER-SKU-1',
            'option_name' => 'Color',
            'option_value' => 'Blue',
            'price_delta' => 50,
            'inventory' => 2,
            'is_active' => '1',
        ])->assertRedirect('/seller/products');

        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'question' => 'Can this ship today?',
            'status' => 'open',
        ]);

        $this->actingAs($seller)->post("/seller/questions/{$question->id}/answers", [
            'answer' => 'Yes, it can ship today.',
        ])->assertRedirect('/seller/products');

        $order = Order::create([
            'number' => 'B202606190001',
            'user_id' => $buyer->id,
            'sales_channel' => 'b2b',
            'purchase_order_number' => '=PO-ACME-2026-001',
            'subtotal' => 500,
            'shipping_fee' => 0,
            'total' => 500,
            'payment_status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 500,
            'quantity' => 1,
            'subtotal' => 500,
        ]);

        $export = $this->actingAs($seller)->get('/seller/orders/export')->assertDownload('seller-orders.csv');
        $this->assertStringContainsString("'=PO-ACME-2026-001", $export->streamedContent());

        $this->actingAs($seller)->patch("/seller/orders/{$order->id}/items/{$orderItem->id}/ship")
            ->assertRedirect('/seller/orders');

        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'SELLER-SKU-1']);
        $this->assertDatabaseHas('product_question_answers', ['product_question_id' => $question->id, 'user_id' => $seller->id]);
        $this->assertSame('answered', $question->fresh()->status);
        $this->assertSame('shipped', $orderItem->fresh()->shipping_status);
        $this->assertSame('completed', $order->fresh()->fulfillment_status);
    }

    public function test_seller_cannot_upload_more_than_eight_product_images(): void
    {
        [$seller] = $this->createUsers();

        $this->actingAs($seller)->from('/seller/products')->post('/seller/products', [
            'name' => 'Too Many Images',
            'price' => 100,
            'inventory' => 1,
            'images' => array_map(
                fn () => UploadedFile::fake()->createWithContent('product.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9J8BcAAAAASUVORK5CYII=') ?: ''),
                range(1, 9),
            ),
        ])
            ->assertRedirect('/seller/products')
            ->assertSessionHasErrors('images');

        $this->assertDatabaseMissing('products', ['name' => 'Too Many Images']);
    }

    public function test_admin_can_manage_users_products_orders_returns_coupons_and_shipping(): void
    {
        [$seller, $buyer, $admin] = $this->createUsers();
        $profile = BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Buyer Co',
            'tax_id' => '12345678',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'status' => 'pending',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Pending Product',
            'price' => 100,
            'inventory' => 2,
            'status' => 'pending',
        ]);
        $order = Order::create([
            'number' => 'A202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
        ]);
        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'reason' => 'No longer needed',
            'status' => 'requested',
        ]);

        $this->actingAs($admin)->patch("/admin/users/{$buyer->id}/status", ['status' => 'suspended'])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->patch("/admin/business-profiles/{$profile->id}", ['status' => 'approved'])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->patch("/admin/products/{$product->id}/status", ['status' => 'active'])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid'])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'adminsave',
            'name' => 'Admin Save',
            'type' => 'fixed',
            'value' => 50,
            'minimum_subtotal' => 100,
            'is_active' => '1',
        ])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->post('/admin/shipping-methods', [
            'name' => 'Admin Delivery',
            'fee' => 80,
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->patch("/admin/returns/{$returnRequest->id}", ['status' => 'approved'])->assertRedirect('/admin/dashboard');

        $this->assertSame('suspended', $buyer->fresh()->status);
        $this->assertSame('approved', $profile->fresh()->status);
        $this->assertSame('b2b', $buyer->fresh()->account_type);
        $this->assertSame('active', $product->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->fulfillment_status);
        $this->assertDatabaseHas('coupons', ['code' => 'ADMINSAVE']);
        $this->assertDatabaseHas('shipping_methods', ['name' => 'Admin Delivery']);
        $this->assertSame('approved', $returnRequest->fresh()->status);
        $this->assertSame('approved', $order->fresh()->return_status);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_customer_cannot_access_seller_or_admin_endpoints(): void
    {
        [, $buyer] = $this->createUsers();

        $this->actingAs($buyer)->get('/seller/products')->assertForbidden();
        $this->actingAs($buyer)->get('/seller/orders')->assertForbidden();
        $this->actingAs($buyer)->get('/seller/orders/export')->assertForbidden();
        $this->actingAs($buyer)->get('/admin/dashboard')->assertForbidden();
    }

    private function createUsers(): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-'.uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer-'.uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin',
            'account' => 'admin-'.uniqid(),
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        return [$seller, $buyer, $admin];
    }
}
