<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_include_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_visitor_can_switch_to_a_supported_locale(): void
    {
        $this->get('/locale/ja')->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('lang="ja"', false);
    }

    public function test_visitor_cannot_select_an_unsupported_locale(): void
    {
        $this->get('/locale/invalid')->assertNotFound();
    }

    public function test_each_supported_locale_translates_global_navigation(): void
    {
        foreach ([
            'zh_TW' => '搜尋',
            'en' => 'Search',
            'ja' => '検索',
            'ko' => '검색',
            'es' => 'Buscar',
        ] as $locale => $searchLabel) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->get('/')->assertSee($searchLabel);
        }
    }

    public function test_each_supported_locale_translates_catalog_and_login_pages(): void
    {
        foreach ([
            'zh_TW' => ['商品列表', '會員登入'],
            'en' => ['Product catalog', 'Member login'],
            'ja' => ['商品一覧', '会員ログイン'],
            'ko' => ['상품 목록', '회원 로그인'],
            'es' => ['Catálogo de productos', 'Inicio de sesión'],
        ] as $locale => [$catalogTitle, $loginTitle]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->get('/')->assertSee($catalogTitle);
            $this->get('/login')->assertSee($loginTitle);
        }
    }

    public function test_each_supported_locale_translates_product_details(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'detail-seller',
            'password' => 'password',
            'role' => 'seller',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Localized Product',
            'price' => 100,
            'inventory' => 3,
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => '分類',
            'en' => 'Category',
            'ja' => 'カテゴリー',
            'ko' => '카테고리',
            'es' => 'Categoría',
        ] as $locale => $categoryLabel) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->get("/products/{$product->id}")->assertSee($categoryLabel);
        }
    }

    public function test_production_cache_can_use_redis(): void
    {
        $this->assertSame('redis', config('cache.stores.redis.driver'));
        $this->assertSame('cache', config('cache.stores.redis.connection'));
        $this->assertSame('redis', config('queue.connections.redis.driver'));
    }

    public function test_catalog_caches_active_categories(): void
    {
        Category::create([
            'name' => 'Cached Category',
            'slug' => 'cached-category',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk();

        $this->assertTrue(Cache::has('catalog.active-categories'));
    }

    public function test_operational_indexes_support_catalog_and_seller_queries(): void
    {
        $this->assertContains('categories_is_active_name_index', $this->indexNamesFor('categories'));
        $this->assertContains('products_status_created_at_index', $this->indexNamesFor('products'));
        $this->assertContains('products_status_price_index', $this->indexNamesFor('products'));
        $this->assertContains('order_items_seller_id_shipping_status_created_at_index', $this->indexNamesFor('order_items'));
    }

    private function indexNamesFor(string $table): array
    {
        return collect(DB::select("PRAGMA index_list('{$table}')"))
            ->pluck('name')
            ->all();
    }
}
