<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\CouponDiscountService;
use App\Services\ProductPricingService;
use App\Services\ProductQuestionService;
use App\Services\ProductRecommendationService;
use App\Services\ProductReviewService;
use App\Services\PromotionService;
use App\Services\ReturnRequestService;
use App\Services\SellerOrderShipmentService;
use App\Services\ShoppingCartService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceLogicFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_status_messages_cover_inactive_stock_and_business_minimum(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer(['account_type' => 'b2b']);
        BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Buyer Co',
            'tax_id' => '87654321',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'status' => 'approved',
        ]);
        $inactive = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Inactive Product',
            'price' => 100,
            'inventory' => 3,
            'status' => 'archived',
        ]);
        $lowStock = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Low Stock Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        $business = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Business Product',
            'price' => 100,
            'business_price' => 80,
            'business_min_quantity' => 5,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $inactiveItem = CartItem::create(['user_id' => $buyer->id, 'product_id' => $inactive->id, 'quantity' => 1]);
        $lowStockItem = CartItem::create(['user_id' => $buyer->id, 'product_id' => $lowStock->id, 'quantity' => 3]);
        $businessItem = CartItem::create(['user_id' => $buyer->id, 'product_id' => $business->id, 'quantity' => 2]);

        $service = app(ShoppingCartService::class);

        $this->assertSame(['Product is inactive. Remove it before checkout.'], $service->statusMessagesForItem($inactiveItem->load('product', 'variant'), $buyer));
        $this->assertSame(['Only 1 in stock. Please update quantity.'], $service->statusMessagesForItem($lowStockItem->load('product', 'variant'), $buyer));
        $this->assertSame(['Business minimum quantity: 5'], $service->statusMessagesForItem($businessItem->load('product', 'variant'), $buyer));
    }

    public function test_coupon_promotion_and_business_price_services_calculate_totals(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer(['account_type' => 'b2b']);
        BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Buyer Co',
            'tax_id' => '22334455',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'status' => 'approved',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Priced Product',
            'price' => 100,
            'business_price' => 70,
            'business_min_quantity' => 5,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $fixedCoupon = Coupon::create([
            'code' => 'FIXED100',
            'name' => 'Fixed 100',
            'type' => 'fixed',
            'value' => 100,
            'minimum_subtotal' => 500,
            'is_active' => true,
        ]);
        $percentCoupon = Coupon::create([
            'code' => 'PERCENT10',
            'name' => 'Percent 10',
            'type' => 'percent',
            'value' => 10,
            'minimum_subtotal' => 0,
            'is_active' => true,
        ]);

        $pricing = app(ProductPricingService::class);
        $couponService = app(CouponDiscountService::class);
        $promotion = app(PromotionService::class);

        $this->assertSame(70, $pricing->calculateUnitPrice($product, null, $buyer, 5, true));
        $this->assertSame(100, $couponService->calculateDiscount($fixedCoupon, 1000));
        $this->assertSame(100, $couponService->calculateDiscount($percentCoupon, 1000));
        $this->assertSame(200, $promotion->calculateOrderDiscount(3000));
        $this->assertSame(0, $promotion->freeShippingRemaining(1200));

        $this->expectException(RuntimeException::class);
        $pricing->calculateUnitPrice($product, null, $buyer, 2, true);
    }

    public function test_recommendation_service_orders_popular_and_related_products(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $popular = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Popular Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $related = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Related Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'S202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 300,
            'shipping_fee' => 0,
            'total' => 300,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $popular->id,
            'seller_id' => $seller->id,
            'product_name' => $popular->name,
            'unit_price' => 100,
            'quantity' => 3,
            'subtotal' => 300,
        ]);

        $recommendations = app(ProductRecommendationService::class);

        $this->assertSame($popular->id, $recommendations->popularProducts(1)->first()->id);
        $this->assertTrue($recommendations->relatedProducts($popular)->contains('id', $related->id));

        $recommendations->recordRecentlyViewed($related, $buyer->id, null);

        $this->assertSame($related->id, $recommendations->recentlyViewed($buyer->id, null, 1)->first()->id);
    }

    public function test_seller_order_shipment_service_updates_item_order_and_audit_log(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $firstProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'First Shipment Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $secondProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Second Shipment Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'SHIP202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 200,
            'shipping_fee' => 0,
            'total' => 200,
            'payment_status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);
        $firstItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'seller_id' => $seller->id,
            'product_name' => $firstProduct->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);
        $secondItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'seller_id' => $seller->id,
            'product_name' => $secondProduct->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $shipments = app(SellerOrderShipmentService::class);
        $shipments->markItemShipped($seller, $order, $firstItem);

        $this->assertSame('shipped', $firstItem->fresh()->shipping_status);
        $this->assertSame('partially_shipped', $order->fresh()->fulfillment_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'seller.order_item.shipped',
            'auditable_id' => $firstItem->id,
        ]);

        $shipments->markItemShipped($seller, $order, $secondItem);

        $this->assertSame('completed', $order->fresh()->fulfillment_status);
    }

    public function test_seller_cannot_ship_an_unpaid_order(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Unpaid Shipment Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'UNPAID202607120001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $this->expectException(AuthorizationException::class);

        app(SellerOrderShipmentService::class)->markItemShipped($seller, $order, $orderItem);
    }

    public function test_return_request_service_creates_request_and_rejects_a_competing_stale_request(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $order = Order::create([
            'number' => 'RET202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
            'return_status' => 'none',
        ]);

        $competingOrder = $order->fresh();
        $returns = app(ReturnRequestService::class);
        $returnRequest = $returns->request($buyer, $order, 'Wrong size');

        $this->assertSame($buyer->id, $returnRequest->user_id);
        $this->assertSame('Wrong size', $returnRequest->reason);
        $this->assertSame('requested', $returnRequest->status);
        $this->assertSame('requested', $order->fresh()->return_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'return.requested',
            'auditable_id' => $returnRequest->id,
        ]);

        try {
            $returns->request($buyer, $competingOrder, 'Competing request');
            $this->fail('A competing stale request should be rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('return_requests', 1);
        }
    }

    public function test_received_return_restocks_order_items_once(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $admin = User::create([
            'name' => 'Admin',
            'account' => 'return-admin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Returned Product',
            'price' => 100,
            'inventory' => 0,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'RET202607120001',
            'user_id' => $buyer->id,
            'subtotal' => 200,
            'shipping_fee' => 0,
            'total' => 200,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
            'return_status' => 'requested',
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
        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'reason' => 'Wrong size',
            'status' => 'requested',
        ]);

        $returns = app(ReturnRequestService::class);
        $returns->updateStatus($admin, $returnRequest, 'approved');
        $returns->updateStatus($admin, $returnRequest->fresh(), 'received');

        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertSame('received', $returnRequest->fresh()->status);
        $this->assertNotNull($returnRequest->fresh()->inventory_restocked_at);
        $this->assertSame('received', $order->fresh()->return_status);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'reason' => 'return_received',
            'quantity_delta' => 2,
            'inventory_after' => 2,
        ]);

        $returns->updateStatus($admin, $returnRequest->fresh(), 'received');

        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);

        $returns->updateStatus($admin, $returnRequest->fresh(), 'refunded');

        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_database_rejects_a_second_return_request_for_the_same_order(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $order = Order::create([
            'number' => 'RET202608230001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        $attributes = [
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'reason' => 'Wrong size',
            'status' => 'requested',
        ];

        ReturnRequest::create($attributes);

        $this->expectException(QueryException::class);
        ReturnRequest::create($attributes);
    }

    public function test_product_review_service_creates_review_and_audit_log(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Review Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'R202606190001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $review = app(ProductReviewService::class)->create($buyer, $product, $orderItem->id, 5, 'Useful review');

        $this->assertSame($buyer->id, $review->user_id);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Useful review', $review->content);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.review.created',
            'auditable_id' => $review->id,
        ]);
    }

    public function test_product_question_service_creates_question_notification_and_audit_log(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Question Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);

        $question = app(ProductQuestionService::class)->ask($buyer, $product, 'Can this ship today?');

        $this->assertSame('Can this ship today?', $question->question);
        $this->assertSame('open', $question->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $seller->id,
            'type' => 'product_question',
            'body' => 'Question Product',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.question.created',
            'auditable_id' => $question->id,
        ]);
    }

    private function createSellerAndBuyer(array $buyerOverrides = []): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller-'.uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create($buyerOverrides + [
            'name' => 'Buyer',
            'account' => 'buyer-'.uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        return [$seller, $buyer];
    }
}
