<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackOfficeEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_and_disable_a_coupon_without_changing_its_code(): void
    {
        $admin = $this->user('admin', 'admin');
        $coupon = Coupon::create(['code' => 'SAVE10', 'name' => 'Old', 'type' => 'fixed', 'value' => 10, 'minimum_subtotal' => 0, 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.coupons.update', $coupon), [
            'code' => 'HACKED',
            'name' => 'New name',
            'type' => 'percent',
            'value' => 15,
            'usage_limit' => 5,
        ])->assertRedirect(route('admin.dashboard'));

        $coupon->refresh();
        $this->assertSame('SAVE10', $coupon->code);
        $this->assertSame('New name', $coupon->name);
        $this->assertSame('percent', $coupon->type);
        $this->assertFalse($coupon->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.coupon.updated']);
    }

    public function test_disabled_shipping_method_can_no_longer_be_chosen_at_checkout(): void
    {
        $admin = $this->user('admin', 'admin');
        $method = ShippingMethod::create(['name' => 'Home', 'fee' => 80, 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.shipping_methods.update', $method), [
            'name' => 'Home Express',
            'fee' => 120,
            'sort_order' => 2,
        ])->assertRedirect(route('admin.dashboard'));

        $method->refresh();
        $this->assertSame(['Home Express', 120, 2, false], [$method->name, $method->fee, $method->sort_order, $method->is_active]);

        $this->actingAs($this->user('buyer', 'customer'))->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('Home Express');
    }

    public function test_non_admin_cannot_edit_coupons_or_shipping_methods(): void
    {
        $seller = $this->user('seller', 'seller');
        $coupon = Coupon::create(['code' => 'KEEP', 'name' => 'Keep', 'type' => 'fixed', 'value' => 10, 'minimum_subtotal' => 0, 'is_active' => true]);
        $method = ShippingMethod::create(['name' => 'Home', 'fee' => 80, 'is_active' => true]);

        $this->actingAs($seller)->patch(route('admin.coupons.update', $coupon), ['name' => 'X', 'type' => 'fixed', 'value' => 1])->assertForbidden();
        $this->actingAs($seller)->patch(route('admin.shipping_methods.update', $method), ['name' => 'X', 'fee' => 0])->assertForbidden();
        $this->assertTrue($coupon->fresh()->is_active);
        $this->assertTrue($method->fresh()->is_active);
    }

    public function test_seller_can_edit_own_variant_and_inventory_change_is_recorded(): void
    {
        $seller = $this->user('seller', 'seller');
        [$product, $variant] = $this->productWithVariant($seller);

        $this->actingAs($seller)->patch(route('seller.products.variants.update', [$product, $variant]), [
            'sku' => 'CHANGED',
            'option_name' => 'Size',
            'option_value' => 'XL',
            'price_delta' => -20,
            'inventory' => 9,
            'expected_inventory' => 4,
        ])->assertRedirect(route('seller.products.index'));

        $variant->refresh();
        $this->assertSame(['SKU-1', 'XL', -20, 9, false], [$variant->sku, $variant->option_value, $variant->price_delta, $variant->inventory, $variant->is_active]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'quantity_delta' => 5,
            'inventory_after' => 9,
            'reason' => 'seller_adjustment',
        ]);
    }

    public function test_variant_edit_rejects_stale_inventory_and_other_sellers(): void
    {
        $seller = $this->user('seller', 'seller');
        [$product, $variant] = $this->productWithVariant($seller);
        [$otherProduct] = $this->productWithVariant($this->user('other', 'seller'), 'SKU-2');
        $payload = ['option_name' => 'Size', 'option_value' => 'M', 'price_delta' => 0, 'inventory' => 10, 'is_active' => '1'];

        $this->actingAs($seller)->from(route('seller.products.index'))
            ->patch(route('seller.products.variants.update', [$product, $variant]), $payload + ['expected_inventory' => 3])
            ->assertSessionHasErrors('inventory');
        $this->actingAs($seller)->patch(route('seller.products.variants.update', [$otherProduct, $variant]), $payload + ['expected_inventory' => 4])
            ->assertNotFound();
        $this->actingAs($this->user('intruder', 'seller'))->patch(route('seller.products.variants.update', [$product, $variant]), $payload + ['expected_inventory' => 4])
            ->assertForbidden();

        $this->assertSame(4, $variant->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 0);
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

    /** @return array{0: Product, 1: ProductVariant} */
    private function productWithVariant(User $seller, string $sku = 'SKU-1'): array
    {
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Shirt', 'price' => 100, 'inventory' => 10, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => $sku, 'option_name' => 'Size', 'option_value' => 'M', 'price_delta' => 0, 'inventory' => 4, 'is_active' => true]);

        return [$product, $variant];
    }
}
