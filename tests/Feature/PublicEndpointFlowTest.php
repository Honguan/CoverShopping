<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEndpointFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_catalog_product_and_use_session_cart(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Public Product',
            'price' => 250,
            'inventory' => 8,
            'status' => 'active',
        ]);

        $this->get('/')->assertOk()->assertSee('Public Product');
        $this->get("/products/{$product->id}")->assertOk()->assertSee('Public Product');

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect('/cart');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_cart_rejects_a_missing_variant_for_a_product_with_active_variants(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = $this->createProductWithVariant($seller, 'Required Variant', true, 3);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<input type="hidden" name="product_id" value="'.$product->id.'">', false);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_rejects_a_variant_from_another_product(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Target Product',
            'price' => 250,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $otherProduct = $this->createProductWithVariant($seller, 'Other Product', true, 3);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $otherProduct->variants()->firstOrFail()->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_rejects_an_inactive_variant(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = $this->createProductWithVariant($seller, 'Inactive Variant', false, 3);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => ProductVariant::where('product_id', $product->id)->firstOrFail()->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_uses_the_selected_active_variant_inventory(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = $this->createProductWithVariant($seller, 'Stocked Variant', true, 3);
        $variant = $product->variants()->firstOrFail();

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('data-inventory="3"', false)
            ->assertSee('max="3"', false);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ])->assertRedirect('/cart');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_registration_logout_and_login_flow_works(): void
    {
        $this->post('/register', [
            'name' => 'New Buyer',
            'account' => 'new-buyer',
            'email' => 'buyer@example.com',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', [
            'account' => 'new-buyer',
            'password' => 'Password12345',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_login_merges_guest_cart_from_the_pre_authentication_session(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Login Cart Product',
            'price' => 250,
            'inventory' => 10,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertRedirect('/cart');
        $guestItem = CartItem::whereNull('user_id')->firstOrFail();
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());

        $this->post('/login', [
            'account' => $buyer->account,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'session_id' => null,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseMissing('cart_items', ['id' => $guestItem->id]);
    }

    public function test_registration_merges_guest_cart_from_the_pre_authentication_session(): void
    {
        [$seller] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Registration Cart Product',
            'price' => 250,
            'inventory' => 10,
            'status' => 'active',
        ]);

        $response = $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertRedirect('/cart');
        $guestItem = CartItem::whereNull('user_id')->firstOrFail();
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());

        $this->post('/register', [
            'name' => 'Registered Cart Buyer',
            'account' => 'registered-cart-buyer',
            'email' => 'registered-cart@example.com',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ])->assertRedirect('/');

        $buyer = User::where('account', 'registered-cart-buyer')->firstOrFail();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'session_id' => null,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseMissing('cart_items', ['id' => $guestItem->id]);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'account' => 'rate-limited-account',
                'password' => 'incorrect',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'account' => 'rate-limited-account',
            'password' => 'incorrect',
        ])->assertTooManyRequests();
    }

    public function test_customer_can_view_b2b_order_references(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        Order::create([
            'number' => 'B202607120001',
            'user_id' => $buyer->id,
            'sales_channel' => 'b2b',
            'purchase_order_number' => 'PO-ACME-2026-001',
            'business_profile_snapshot' => [
                'company_name' => 'Acme Ltd',
                'tax_id' => '12345678',
            ],
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
        ]);

        $this->actingAs($buyer)->get('/orders')
            ->assertOk()
            ->assertSee('PO-ACME-2026-001')
            ->assertSee('Acme Ltd')
            ->assertSee('12345678');
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $otherBuyer = User::create([
            'name' => 'Other Buyer',
            'account' => 'other-buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'C202607120001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
        ]);

        $this->actingAs($otherBuyer)->post("/orders/{$order->id}/cancel")->assertForbidden();
    }

    public function test_customer_can_cancel_own_unpaid_order(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Cancelable Product',
            'price' => 100,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'C202607120002',
            'user_id' => $buyer->id,
            'subtotal' => 200,
            'shipping_fee' => 0,
            'total' => 200,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
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

        $this->actingAs($buyer)->post("/orders/{$order->id}/cancel")
            ->assertRedirect('/orders')
            ->assertSessionHas('status', '訂單已取消。');

        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertSame(2, $product->fresh()->inventory);
    }

    public function test_customer_can_review_question_favorite_return_and_read_notification(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Engagement Product',
            'price' => 300,
            'inventory' => 3,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'P202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 300,
            'shipping_fee' => 0,
            'total' => 300,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 300,
            'quantity' => 1,
            'subtotal' => 300,
            'shipping_status' => 'shipped',
        ]);
        $notification = Notification::create([
            'user_id' => $buyer->id,
            'type' => 'order_update',
            'title' => 'Order updated',
            'url' => route('orders.index'),
        ]);

        $this->actingAs($buyer)->post("/products/{$product->id}/favorite")->assertRedirect();
        $this->actingAs($buyer)->post("/products/{$product->id}/questions", ['question' => 'Is this available?'])->assertRedirect();
        $this->actingAs($buyer)->post("/products/{$product->id}/reviews", [
            'order_item_id' => $orderItem->id,
            'rating' => 5,
            'content' => 'Good product',
        ])->assertRedirect();
        $this->actingAs($buyer)->post("/orders/{$order->id}/returns", ['reason' => 'Wrong size'])->assertRedirect();
        $this->actingAs($buyer)->get('/notifications')->assertOk()->assertSee('Order updated');
        $this->actingAs($buyer)->patch("/notifications/{$notification->id}/read")->assertRedirect('/orders');

        $this->assertDatabaseHas('favorites', ['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('product_questions', ['product_id' => $product->id, 'user_id' => $buyer->id]);
        $this->assertDatabaseHas('product_reviews', ['product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => 5]);
        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id, 'user_id' => $buyer->id, 'status' => 'requested']);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_guest_cannot_access_authenticated_web_endpoints(): void
    {
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/addresses')->assertRedirect('/login');
        $this->get('/notifications')->assertRedirect('/login');
        $this->get('/business-profile')->assertRedirect('/login');
    }

    private function createSellerAndBuyer(): array
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

        return [$seller, $buyer];
    }

    private function createProductWithVariant(User $seller, string $name, bool $isActive, int $inventory): Product
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => $name,
            'price' => 250,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $product->variants()->create([
            'sku' => 'SKU-'.uniqid(),
            'option_name' => 'Color',
            'option_value' => 'Blue',
            'inventory' => $inventory,
            'is_active' => $isActive,
        ]);

        return $product;
    }
}
