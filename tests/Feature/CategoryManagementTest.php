<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_changes_show_on_the_catalog_immediately(): void
    {
        $admin = $this->user('admin', 'admin');
        $this->get(route('catalog.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Shirts', 'slug' => 'shirts', 'is_active' => '1'])
            ->assertRedirect(route('admin.dashboard'));
        $category = Category::where('slug', 'shirts')->firstOrFail();
        $this->get(route('catalog.index'))->assertSee('Shirts');

        $this->patch(route('admin.categories.update', $category), ['name' => 'Tees', 'slug' => 'tees'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertFalse($category->fresh()->is_active);
        $this->get(route('catalog.index'))->assertDontSee('Tees')->assertDontSee('Shirts');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.category.updated']);
    }

    public function test_slug_must_be_unique_and_url_safe_and_only_admins_may_manage(): void
    {
        $admin = $this->user('admin', 'admin');
        Category::create(['name' => 'Shirts', 'slug' => 'shirts']);
        $other = Category::create(['name' => 'Pants', 'slug' => 'pants']);

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Dup', 'slug' => 'shirts'])->assertSessionHasErrors('slug');
        $this->post(route('admin.categories.store'), ['name' => 'Bad', 'slug' => 'a b/c'])->assertSessionHasErrors('slug');
        $this->patch(route('admin.categories.update', $other), ['name' => 'Pants', 'slug' => 'pants'])->assertSessionHasNoErrors();

        $this->actingAs($this->user('seller', 'seller'))->post(route('admin.categories.store'), ['name' => 'X', 'slug' => 'x'])->assertForbidden();
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_seller_picks_an_active_category_for_a_product(): void
    {
        $seller = $this->user('seller', 'seller');
        $active = Category::create(['name' => 'Shirts', 'slug' => 'shirts']);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->actingAs($seller)->get(route('seller.products.index'))
            ->assertOk()
            ->assertSee('Shirts')
            ->assertDontSee('Hidden');

        $this->post(route('seller.products.store'), ['name' => 'Tee', 'price' => 100, 'inventory' => 3, 'category_id' => $active->id]);
        $this->assertSame($active->id, Product::where('name', 'Tee')->value('category_id'));
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
}
