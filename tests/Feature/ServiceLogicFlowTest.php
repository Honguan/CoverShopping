<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponDiscountService;
use App\Services\ProductPricingService;
use App\Services\ProductRecommendationService;
use App\Services\ProductQuestionService;
use App\Services\ProductReviewService;
use App\Services\PromotionService;
use App\Services\ShoppingCartService;
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
            'account' => 'seller-' . uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create($buyerOverrides + [
            'name' => 'Buyer',
            'account' => 'buyer-' . uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        return [$seller, $buyer];
    }
}
