<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductFavoriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_favorites_or_see_the_navigation_entry(): void
    {
        $this->get('/favorites')->assertRedirect('/login');
        $this->get('/')->assertDontSee('href="'.url('/favorites').'"', false);
    }

    public function test_favorites_are_scoped_and_unavailable_products_have_no_purchase_link(): void
    {
        $buyer = $this->user();
        $other = $this->user();
        $seller = $this->user('seller');
        $active = $this->product($seller, 'My saved product');
        $unavailable = $this->product($seller, 'My archived product');
        $otherProduct = $this->product($seller, 'Other user saved product');
        $this->product($seller, 'Never saved product');
        $favorites = app(ProductFavoriteService::class);
        $favorites->add($buyer, $active);
        $favorites->add($buyer, $unavailable);
        $favorites->add($other, $otherProduct);
        $unavailable->update(['status' => 'archived']);

        $this->actingAs($buyer)->get('/favorites')
            ->assertOk()
            ->assertSee($active->name)
            ->assertSee($unavailable->name)
            ->assertSee(__('ui.favorite_unavailable'))
            ->assertDontSee($otherProduct->name)
            ->assertDontSee('Never saved product')
            ->assertSee('href="'.route('catalog.show', $active).'"', false)
            ->assertDontSee('href="'.route('catalog.show', $unavailable).'"', false)
            ->assertViewHas('products', fn ($products) => $products->total() === 2
                && $products->getCollection()->every(fn (Product $product) => $product->relationLoaded('primaryImage')
                    && $product->relationLoaded('variants')));
        $this->get('/')->assertSee('href="'.route('favorites.index').'"', false);
    }

    public function test_favorites_are_paginated_in_stable_saved_order(): void
    {
        $buyer = $this->user();
        $seller = $this->user('seller');
        $products = [];
        for ($index = 0; $index < 25; $index++) {
            $products[] = $this->product($seller, 'Saved product '.$index);
        }
        foreach (array_reverse($products) as $product) {
            app(ProductFavoriteService::class)->add($buyer, $product);
        }
        $expected = array_map(fn (Product $product) => $product->id, array_slice($products, 0, 24));

        $this->actingAs($buyer)->get('/favorites')
            ->assertOk()
            ->assertViewHas('products', fn ($page) => $page->total() === 25
                && $page->getCollection()->modelKeys() === $expected)
            ->assertSee('/favorites?page=2', false);
        $this->get('/favorites?page=2')
            ->assertOk()
            ->assertViewHas('products', fn ($page) => $page->getCollection()->modelKeys() === [$products[24]->id]);
    }

    public function test_removing_an_unavailable_favorite_preserves_other_users_and_shows_localized_empty_state(): void
    {
        $buyer = $this->user();
        $other = $this->user();
        $product = $this->product($this->user('seller'), 'Shared saved product');
        app(ProductFavoriteService::class)->add($buyer, $product);
        app(ProductFavoriteService::class)->add($other, $product);
        $product->update(['status' => 'archived']);

        $this->actingAs($buyer)->from('/favorites')->delete('/products/'.$product->id.'/favorite')
            ->assertRedirect('/favorites')
            ->assertSessionHas('status', __('ui.product_removed_from_favorites'));
        $this->assertDatabaseMissing('favorites', ['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('favorites', ['user_id' => $other->id, 'product_id' => $product->id]);
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->get('/locale/'.$locale)->assertRedirect();
            $this->get('/favorites')->assertOk()
                ->assertSee(__('ui.favorites', [], $locale))
                ->assertSee(__('ui.empty_favorites', [], $locale))
                ->assertDontSee($product->name);
        }
    }

    private function user(string $role = 'customer'): User
    {
        return User::create([
            'name' => $role, 'account' => $role.'-'.uniqid(),
            'password' => 'password', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function product(User $seller, string $name): Product
    {
        return Product::create([
            'seller_id' => $seller->id, 'name' => $name,
            'price' => 100, 'inventory' => 5, 'status' => 'active',
        ]);
    }
}
