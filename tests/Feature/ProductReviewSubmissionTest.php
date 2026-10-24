<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_submission_preserves_the_first_review_and_writes_one_audit_log(): void
    {
        [$buyer, $product, $item] = $this->createPurchase();

        $this->actingAs($buyer)->from('/orders')->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 5,
            'content' => 'First review',
        ])->assertRedirect('/orders')->assertSessionHas('status', __('ui.review_submitted'));

        $this->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 1,
            'content' => 'Replacement review',
        ])->assertRedirect('/orders')->assertSessionHas('status', __('ui.review_submitted'));

        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'order_item_id' => $item->id,
            'rating' => 5,
            'content' => 'First review',
            'status' => 'published',
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.review.created',
            'auditable_id' => ProductReview::firstOrFail()->id,
        ]);
    }

    public function test_another_customer_cannot_resubmit_an_existing_review(): void
    {
        [$buyer, $product, $item] = $this->createPurchase();
        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 5,
        ])->assertRedirect();
        $otherBuyer = User::create([
            'name' => 'Other buyer',
            'account' => 'other-buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($otherBuyer)->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unfulfilled_orders_cannot_be_reviewed(): void
    {
        [$buyer, $product, $item] = $this->createPurchase('processing');

        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 5,
        ])->assertForbidden();
        $this->get('/orders')->assertOk()->assertDontSee('name="order_item_id"', false);

        $this->assertDatabaseCount('product_reviews', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_refunded_orders_cannot_be_reviewed(): void
    {
        [$buyer, $product, $item] = $this->createPurchase();
        $item->order->update(['payment_status' => 'refunded']);

        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'order_item_id' => $item->id,
            'rating' => 5,
        ])->assertForbidden();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_the_order_item_must_belong_to_the_requested_product(): void
    {
        [$buyer, $product, $item] = $this->createPurchase();
        $otherProduct = $product->replicate();
        $otherProduct->name = 'Other product';
        $otherProduct->save();

        $this->actingAs($buyer)->post(route('reviews.store', $otherProduct), [
            'order_item_id' => $item->id,
            'rating' => 5,
        ])->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_orders_show_review_status_and_keep_the_form_for_unreviewed_items(): void
    {
        [$buyer, $product, $reviewedItem] = $this->createPurchase('shipped');
        $unreviewedItem = $reviewedItem->replicate();
        $unreviewedItem->save();
        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'order_item_id' => $reviewedItem->id,
            'rating' => 5,
            'status' => 'published',
        ]);

        $response = $this->actingAs($buyer)->get('/orders')->assertOk()
            ->assertSee(__('ui.review_submitted'))
            ->assertDontSee('name="order_item_id" value="'.$reviewedItem->id.'"', false)
            ->assertSee('name="order_item_id" value="'.$unreviewedItem->id.'"', false);

        $orders = $response->viewData('orders');
        foreach ($orders->first()->items as $item) {
            $this->assertTrue($item->relationLoaded('review'));
        }
    }

    private function createPurchase(string $fulfillmentStatus = 'completed'): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Review product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'REVIEW-001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => $fulfillmentStatus,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        return [$buyer, $product, $item];
    }
}
