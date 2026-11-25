<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSkuAndSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_sku_finds_the_product_even_when_name_does_not_match(): void
    {
        $seller = $this->user('seller', 'seller');
        $shirt = Product::create(['seller_id' => $seller->id, 'name' => 'Linen Shirt', 'price' => 100, 'inventory' => 5, 'status' => 'active']);
        $shirt->variants()->create(['sku' => 'LS-RED-M', 'option_name' => 'Size', 'option_value' => 'M', 'price_delta' => 0, 'inventory' => 2, 'is_active' => true]);
        Product::create(['seller_id' => $seller->id, 'name' => 'Wool Coat', 'price' => 900, 'inventory' => 5, 'status' => 'active']);
        $hidden = Product::create(['seller_id' => $seller->id, 'name' => 'Draft', 'price' => 100, 'inventory' => 5, 'status' => 'pending']);
        $hidden->variants()->create(['sku' => 'DRAFT-1', 'option_name' => 'Size', 'option_value' => 'M', 'price_delta' => 0, 'inventory' => 2, 'is_active' => true]);

        $this->assertSame(['Linen Shirt'], $this->listed(['q' => 'LS-RED-M']));
        $this->assertSame([], $this->listed(['q' => 'DRAFT-1']));
    }

    public function test_parent_category_lists_products_of_its_subcategories(): void
    {
        $seller = $this->user('seller', 'seller');
        $clothing = Category::create(['name' => 'Clothing', 'slug' => 'clothing']);
        $shirts = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $clothing->id]);
        $toys = Category::create(['name' => 'Toys', 'slug' => 'toys']);
        Product::create(['seller_id' => $seller->id, 'category_id' => $shirts->id, 'name' => 'Oxford Shirt', 'price' => 100, 'inventory' => 5, 'status' => 'active']);
        Product::create(['seller_id' => $seller->id, 'category_id' => $toys->id, 'name' => 'Robot', 'price' => 100, 'inventory' => 5, 'status' => 'active']);

        $this->assertSame(['Oxford Shirt'], $this->listed(['category' => 'clothing']));
        $this->assertSame(['Oxford Shirt'], $this->listed(['category' => 'shirts']));
        $this->assertSame(['Robot'], $this->listed(['category' => 'toys']));
        $this->get(route('catalog.index'))->assertSeeInOrder(['Clothing', '└ Shirts', 'Toys']);

        $clothing->update(['is_active' => false]);
        $this->get(route('catalog.index'))->assertDontSee('└ Shirts');
    }

    public function test_admin_can_nest_one_level_only(): void
    {
        $admin = $this->user('admin', 'admin');
        $clothing = Category::create(['name' => 'Clothing', 'slug' => 'clothing']);
        $shirts = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $clothing->id]);
        $toys = Category::create(['name' => 'Toys', 'slug' => 'toys']);

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Tees', 'slug' => 'tees', 'parent_id' => $shirts->id])
            ->assertSessionHasErrors('parent_id');
        $this->patch(route('admin.categories.update', $clothing), ['name' => 'Clothing', 'slug' => 'clothing', 'parent_id' => $toys->id])
            ->assertSessionHasErrors('parent_id');
        $this->patch(route('admin.categories.update', $toys), ['name' => 'Toys', 'slug' => 'toys', 'parent_id' => $toys->id])
            ->assertSessionHasErrors('parent_id');

        $this->patch(route('admin.categories.update', $shirts), ['name' => 'Shirts', 'slug' => 'shirts', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull($shirts->fresh()->parent_id);
        $this->post(route('admin.categories.store'), ['name' => 'Dolls', 'slug' => 'dolls', 'parent_id' => $toys->id])->assertSessionHasNoErrors();
        $this->assertSame($toys->id, Category::where('slug', 'dolls')->value('parent_id'));
    }

    public function test_fully_returned_and_refunded_order_gives_the_coupon_back(): void
    {
        $admin = $this->user('admin', 'admin');
        $buyer = $this->user('buyer', 'customer');
        $coupon = Coupon::create(['code' => 'ONCE', 'name' => 'Once', 'type' => 'fixed', 'value' => 10, 'minimum_subtotal' => 0, 'usage_limit' => 1, 'used_count' => 1, 'is_active' => true]);
        $order = Order::create([
            'number' => 'COUPON-RETURN-1', 'user_id' => $buyer->id, 'coupon_id' => $coupon->id, 'coupon_code' => 'ONCE',
            'subtotal' => 100, 'discount_total' => 10, 'total' => 90,
            'payment_status' => 'paid', 'fulfillment_status' => 'completed', 'return_status' => 'received',
        ]);
        CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $buyer->id, 'order_id' => $order->id]);
        $returnRequest = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $buyer->id, 'reason' => 'Wrong size', 'status' => 'received', 'inventory_restocked_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.returns.status', $returnRequest), ['status' => 'refunded'])->assertSessionHasNoErrors();
        $this->patch(route('admin.returns.status', $returnRequest), ['status' => 'refunded'])->assertSessionHasNoErrors();

        $this->assertSame(0, Coupon::findOrFail($coupon->id)->used_count);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    /** @return list<string> */
    private function listed(array $query): array
    {
        return $this->get(route('catalog.index', $query))->assertOk()->viewData('products')->pluck('name')->all();
    }

    private function user(string $account, string $role): User
    {
        return User::create(['name' => $account, 'account' => $account, 'password' => 'password', 'role' => $role, 'status' => 'active']);
    }
}
