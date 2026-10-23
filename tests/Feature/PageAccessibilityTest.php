<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_have_distinct_titles_and_labelled_navigation(): void
    {
        $seller = User::create(['name' => 'Seller', 'account' => 'a11y-seller', 'password' => 'password', 'role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Title Product', 'price' => 100, 'inventory' => 1, 'status' => 'active']);
        $suffix = ' · '.config('app.name').'</title>';

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>'.__('ui.login_title').$suffix, false)
            ->assertSee('aria-label="'.__('ui.search_products').'"', false)
            ->assertSee('<nav aria-label="'.__('ui.main_navigation').'">', false);
        $this->get('/cart')->assertSee('<title>'.__('ui.shopping_cart').$suffix, false);
        $this->get("/products/{$product->id}")->assertSee('<title>Title Product'.$suffix, false);
    }

    public function test_every_locale_defines_the_navigation_label(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->assertNotSame('ui.main_navigation', trans('ui.main_navigation', [], $locale));
        }
    }
}
