<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderCheckoutService;
use App\Services\ShoppingCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CartCheckoutValidityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidCartLines')]
    public function test_checkout_rejects_invalid_lines_without_any_writes(int $quantity, bool $addVariant, string $message): void
    {
        $buyer = $this->user();
        $product = $this->product();
        $item = CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => $quantity]);
        $variant = $addVariant ? $this->variant($product) : null;
        $validProduct = $this->product();
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $validProduct->id, 'quantity' => 1]);
        $coupon = Coupon::create([
            'code' => 'VALID', 'name' => 'Valid coupon', 'type' => 'fixed', 'value' => 1,
            'minimum_subtotal' => 0, 'used_count' => 0, 'is_active' => true,
        ]);

        $error = null;
        try {
            app(OrderCheckoutService::class)->createOrderFromCart($buyer, couponCode: $coupon->code);
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
        $this->assertSame(__($message), $error);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(5, $product->fresh()->inventory);
        $this->assertSame(5, $validProduct->fresh()->inventory);
        if ($variant) {
            $this->assertSame(5, $variant->fresh()->inventory);
        }
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertSame($quantity, $item->fresh()->quantity);
        $this->assertContains(__($message), app(ShoppingCartService::class)->statusMessagesForItem($item->load('product', 'variant'), $buyer));
    }

    public static function invalidCartLines(): array
    {
        return [
            'SKU added after cart entry' => [1, true, 'ui.select_product_variant'],
            'zero quantity' => [0, false, 'ui.cart_invalid_quantity'],
            'negative quantity' => [-1, false, 'ui.cart_invalid_quantity'],
        ];
    }

    public function test_cart_reports_missing_skus_without_per_item_queries(): void
    {
        $buyer = $this->user();
        $service = app(ShoppingCartService::class);
        $expected = [];
        foreach ([true, true, false] as $active) {
            $product = $this->product();
            $item = $service->addProduct($buyer, 'session', $product, 1);
            $this->variant($product)->update(['is_active' => $active]);
            $expected[$item->id] = $active ? [__('ui.select_product_variant')] : [];
        }
        $buyer->load('businessProfile');
        $items = $service->getItemsForUserOrSession($buyer, 'session');

        DB::enableQueryLog();
        try {
            $messages = $service->statusMessagesForItems($items, $buyer);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame($expected, $messages);
        $this->actingAs($buyer)->get('/cart')->assertOk()->assertSee(__('ui.select_product_variant'));
    }

    #[DataProvider('soldOutCartLines')]
    public function test_guest_merge_retains_a_positive_quantity_for_sold_out_items(bool $hasVariant, bool $hasExisting): void
    {
        $buyer = $this->user();
        $product = $this->product();
        $variant = $hasVariant ? $this->variant($product) : null;
        $service = app(ShoppingCartService::class);
        $service->addProduct(null, 'guest', $product, 3, $variant);
        if ($hasExisting) {
            $service->addProduct($buyer, 'member', $product, 2, $variant);
        }
        ($variant ?? $product)->update(['inventory' => 0]);

        $service->mergeGuestCartIntoUserCart($buyer, 'guest');

        $this->assertDatabaseCount('cart_items', 1);
        $item = $service->getItemsForUserOrSession($buyer, 'member')->sole();
        $this->assertSame(1, $item->quantity);
        $this->assertNull($item->session_id);
        $this->assertSame($variant?->id, $item->product_variant_id);
        $this->assertSame([__('ui.out_of_stock_checkout')], $service->statusMessagesForItem($item, $buyer));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('ui.stock_quantity_available', ['quantity' => 0]));
        app(OrderCheckoutService::class)->createOrderFromCart($buyer);
    }

    public static function soldOutCartLines(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    public function test_inactive_skus_do_not_prevent_parent_inventory_checkout(): void
    {
        $buyer = $this->user();
        $product = $this->product();
        $this->variant($product)->update(['is_active' => false]);
        app(ShoppingCartService::class)->addProduct($buyer, 'session', $product, 1);

        $order = app(OrderCheckoutService::class)->createOrderFromCart($buyer);

        $this->assertSame(100, $order->total);
        $this->assertSame(4, $product->fresh()->inventory);
    }

    private function product(): Product
    {
        return Product::create([
            'seller_id' => $this->user('seller')->id,
            'name' => 'Cart product', 'price' => 100, 'inventory' => 5, 'status' => 'active',
        ]);
    }

    private function user(string $role = 'customer'): User
    {
        return User::create([
            'name' => $role, 'account' => $role.'-'.uniqid(),
            'password' => 'password', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function variant(Product $product): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'SKU-'.$product->id,
            'option_name' => 'Color', 'option_value' => 'Red',
            'price_delta' => 0, 'inventory' => 5, 'is_active' => true,
        ]);
    }
}
