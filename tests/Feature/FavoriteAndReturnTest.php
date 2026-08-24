<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\BusinessProfile;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteAndReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_favorite_active_product(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Favorite Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)->post("/products/{$product->id}/favorite")->assertRedirect();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_authenticated_user_can_remove_favorite_product(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Removed Favorite Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)->post("/products/{$product->id}/favorite")->assertRedirect();
        $this->actingAs($buyer)->delete("/products/{$product->id}/favorite")->assertRedirect();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_customer_can_manage_address_book(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();

        $this->actingAs($buyer)->post('/addresses', [
            'recipient_name' => 'Buyer',
            'phone' => '0911000000',
            'postal_code' => '100',
            'city' => 'Taipei',
            'district' => 'Da-an',
            'address_line' => 'No. 1',
            'is_default' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $buyer->id,
            'recipient_name' => 'Buyer',
            'is_default' => true,
        ]);
    }

    public function test_repeated_default_changes_keep_one_default_and_deletion_promotes_latest_address(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $first = $this->createAddress($buyer, 'First', true);
        $second = $this->createAddress($buyer, 'Second');
        $third = $this->createAddress($buyer, 'Third');

        $this->actingAs($buyer)->patch("/addresses/{$second->id}/default")->assertRedirect();
        $this->actingAs($buyer)->patch("/addresses/{$second->id}/default")->assertRedirect();

        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        $this->actingAs($buyer)->delete("/addresses/{$second->id}")->assertRedirect();

        $this->assertTrue($third->fresh()->is_default);
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
    }

    public function test_database_rejects_two_default_addresses_for_one_user(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $this->createAddress($buyer, 'First', true);

        try {
            $this->createAddress($buyer, 'Second', true);
            $this->fail('Expected the database to reject a second default address.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
    }

    public function test_customer_can_reorder_available_products(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Reorder Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'T202606180001',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
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

        $this->actingAs($buyer)->post("/orders/{$order->id}/reorder")->assertRedirect('/cart');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_can_clear_only_their_own_cart(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $other = User::create([
            'name' => 'Other',
            'account' => 'other',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Cart Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'user_id' => $other->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($buyer)->delete('/cart/items')->assertRedirect('/cart');

        $this->assertDatabaseMissing('cart_items', ['user_id' => $buyer->id]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $other->id]);
    }

    public function test_cart_shows_actionable_item_status_and_checkout_defaults(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer(['account_type' => 'b2b']);
        BusinessProfile::create([
            'user_id' => $buyer->id,
            'company_name' => 'Acme Ltd',
            'tax_id' => '12345678',
            'contact_name' => 'Buyer',
            'contact_phone' => '0911000000',
            'status' => 'approved',
        ]);
        $address = Address::create([
            'user_id' => $buyer->id,
            'recipient_name' => 'Buyer',
            'phone' => '0911000000',
            'city' => 'Taipei',
            'address_line' => 'No. 1',
            'is_default' => true,
        ]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Home Delivery',
            'fee' => 80,
            'is_active' => true,
        ]);
        $lowStockProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Low Stock Cart Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        $inactiveProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Inactive Cart Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'archived',
        ]);
        $variantProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Variant Cart Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'active',
        ]);
        $inactiveVariant = ProductVariant::create([
            'product_id' => $variantProduct->id,
            'sku' => 'VC-INACTIVE',
            'option_name' => 'Color',
            'option_value' => 'Black',
            'inventory' => 5,
            'is_active' => false,
        ]);
        $businessProduct = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Business Cart Product',
            'price' => 100,
            'business_price' => 80,
            'business_min_quantity' => 5,
            'inventory' => 10,
            'status' => 'active',
        ]);

        CartItem::create(['user_id' => $buyer->id, 'product_id' => $lowStockProduct->id, 'quantity' => 3]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $inactiveProduct->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $variantProduct->id, 'product_variant_id' => $inactiveVariant->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $businessProduct->id, 'quantity' => 2]);

        $response = $this->actingAs($buyer)->get('/cart');

        $response->assertOk()
            ->assertSee(__('ui.product_inactive_checkout'))
            ->assertSee(__('ui.product_variant_unavailable_checkout'))
            ->assertSee(__('ui.stock_quantity_available', ['quantity' => 1]))
            ->assertSee(__('ui.business_minimum_quantity_short', ['quantity' => 5]))
            ->assertSee('value="'.$address->id.'" selected', false)
            ->assertSee('value="'.$shippingMethod->id.'" selected', false);
    }

    public function test_reorder_adds_available_quantity_and_reports_skipped_items(): void
    {
        [$seller, $buyer] = $this->createSellerAndBuyer();
        $available = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Limited Reorder Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        $inactive = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Inactive Reorder Product',
            'price' => 100,
            'inventory' => 5,
            'status' => 'archived',
        ]);
        $order = Order::create([
            'number' => 'T202606180003',
            'user_id' => $buyer->id,
            'subtotal' => 400,
            'shipping_fee' => 0,
            'total' => 400,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $available->id,
            'seller_id' => $seller->id,
            'product_name' => $available->name,
            'unit_price' => 100,
            'quantity' => 3,
            'subtotal' => 300,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $inactive->id,
            'seller_id' => $seller->id,
            'product_name' => $inactive->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $this->actingAs($buyer)->post("/orders/{$order->id}/reorder")
            ->assertRedirect('/cart')
            ->assertSessionHas('status', __('ui.reorder_summary', ['added' => 1, 'skipped' => 1]));

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $available->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $inactive->id,
        ]);
    }

    public function test_customer_can_request_return_for_own_completed_order(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $order = Order::create([
            'number' => 'T202606180002',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);

        $this->actingAs($buyer)
            ->post("/orders/{$order->id}/returns", ['reason' => 'Wrong size'])
            ->assertRedirect();

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'status' => 'requested',
        ]);
        $this->assertSame('requested', $order->fresh()->return_status);

        $this->actingAs($buyer)
            ->post("/orders/{$order->id}/returns", ['reason' => 'Duplicate request'])
            ->assertForbidden();

        $this->assertDatabaseCount('return_requests', 1);
    }

    public function test_customer_cannot_delete_other_users_address(): void
    {
        [, $buyer] = $this->createSellerAndBuyer();
        $other = User::create([
            'name' => 'Other',
            'account' => 'other',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $address = Address::create([
            'user_id' => $other->id,
            'recipient_name' => 'Other',
            'phone' => '0922000000',
            'city' => 'Taipei',
            'address_line' => 'No. 9',
        ]);

        $this->actingAs($buyer)->delete("/addresses/{$address->id}")->assertForbidden();
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

    private function createAddress(User $user, string $recipient, bool $isDefault = false): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'recipient_name' => $recipient,
            'phone' => '0911000000',
            'city' => 'Taipei',
            'address_line' => 'No. 1',
            'is_default' => $isDefault,
        ]);
    }
}
