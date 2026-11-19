<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceRuleHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_an_active_shipping_method_when_one_exists(): void
    {
        $buyer = $this->user('buyer', 'customer');
        $product = $this->product($this->user('seller', 'seller'));
        $active = ShippingMethod::create(['name' => 'Home', 'fee' => 80, 'is_active' => true]);
        $inactive = ShippingMethod::create(['name' => 'Old', 'fee' => 0, 'is_active' => false]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($buyer)->from(route('cart.index'))
            ->post(route('checkout.store'), [])
            ->assertSessionHasErrors('shipping_method_id');
        $this->actingAs($buyer)->from(route('cart.index'))
            ->post(route('checkout.store'), ['shipping_method_id' => $inactive->id])
            ->assertSessionHasErrors('shipping_method_id');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($buyer)->post(route('checkout.store'), ['shipping_method_id' => $active->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['user_id' => $buyer->id, 'shipping_fee' => 80]);
    }

    public function test_checkout_without_any_active_shipping_method_stays_allowed(): void
    {
        $buyer = $this->user('buyer', 'customer');
        $product = $this->product($this->user('seller', 'seller'));
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($buyer)->post(route('checkout.store'), [])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['user_id' => $buyer->id, 'shipping_fee' => 0]);
    }

    public function test_variant_discount_cannot_exceed_the_cheapest_base_price(): void
    {
        $seller = $this->user('seller', 'seller');
        $product = $this->product($seller, ['business_price' => 60]);
        $variant = fn (int $delta, string $sku) => $this->actingAs($seller)
            ->post(route('seller.products.variants.store', $product), [
                'sku' => $sku,
                'option_name' => 'Size',
                'option_value' => 'L',
                'price_delta' => $delta,
                'inventory' => 1,
            ]);

        $variant(-61, 'TOO-CHEAP')->assertSessionHasErrors('price_delta');
        $variant(-60, 'OK-CHEAP')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('product_variants', ['sku' => 'TOO-CHEAP']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'OK-CHEAP', 'price_delta' => -60]);
    }

    private function user(string $account, string $role): User
    {
        return User::create([
            'name' => $account,
            'account' => $account,
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(User $seller, array $overrides = []): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'name' => 'Item',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ] + $overrides);
    }
}
