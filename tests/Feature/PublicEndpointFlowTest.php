<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

        return [$seller, $buyer];
    }
}
