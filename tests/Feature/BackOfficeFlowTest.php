<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackOfficeFlowTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($seller)->patch("/seller/orders/{$order->id}/items/{$orderItem->id}/ship")
            ->assertRedirect('/seller/orders');

        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'SELLER-SKU-1']);
        $this->assertDatabaseHas('product_question_answers', ['product_question_id' => $question->id, 'user_id' => $seller->id]);
        $this->assertSame('answered', $question->fresh()->status);
        $this->assertSame('shipped', $orderItem->fresh()->shipping_status);
        $this->assertSame('completed', $order->fresh()->fulfillment_status);
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
    }

    public function test_customer_cannot_access_seller_or_admin_endpoints(): void
    {
        [, $buyer] = $this->createUsers();

        $this->actingAs($buyer)->get('/seller/products')->assertForbidden();
        $this->actingAs($buyer)->get('/seller/orders')->assertForbidden();
        $this->actingAs($buyer)->get('/admin/dashboard')->assertForbidden();
    }

    private function createUsers(): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-' . uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer-' . uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin',
            'account' => 'admin-' . uniqid(),
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        return [$seller, $buyer, $admin];
    }
}
