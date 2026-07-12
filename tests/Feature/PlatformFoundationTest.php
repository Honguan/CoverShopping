<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
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
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'")
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_dependency_health_endpoint_is_available(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
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

    public function test_each_supported_locale_translates_cart_page(): void
    {
        foreach ([
            'zh_TW' => '購物車',
            'en' => 'Shopping cart',
            'ja' => 'ショッピングカート',
            'ko' => '장바구니',
            'es' => 'Carrito de compras',
        ] as $locale => $cartTitle) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->get('/cart')->assertSee("<h1>{$cartTitle}</h1>", false);
        }
    }

    public function test_each_supported_locale_translates_cart_checkout_controls(): void
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'cart-localized-seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Buyer',
            'account' => 'cart-localized-buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Cart Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        foreach ([
            'zh_TW' => ['結帳', '配送地址'],
            'en' => ['Checkout', 'Shipping address'],
            'ja' => ['注文を確定', '配送先住所'],
            'ko' => ['주문하기', '배송지 주소'],
            'es' => ['Finalizar compra', 'Dirección de envío'],
        ] as $locale => [$checkoutLabel, $shippingAddressLabel]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($buyer)->get('/cart')
                ->assertSee($checkoutLabel)
                ->assertSee($shippingAddressLabel);
        }
    }

    public function test_each_supported_locale_translates_orders_page(): void
    {
        $user = User::create([
            'name' => 'Localized Buyer',
            'account' => 'localized-buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        Order::create([
            'number' => 'L202607120001',
            'user_id' => $user->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'shipped',
            'return_status' => 'requested',
        ]);

        foreach ([
            'zh_TW' => ['我的訂單', '已付款', '已出貨', '已申請'],
            'en' => ['My orders', 'Paid', 'Shipped', 'Requested'],
            'ja' => ['注文履歴', '支払い済み', '発送済み', '申請済み'],
            'ko' => ['내 주문', '결제 완료', '배송됨', '신청됨'],
            'es' => ['Mis pedidos', 'Pagado', 'Enviado', 'Solicitada'],
        ] as $locale => [$ordersTitle, $paymentStatus, $fulfillmentStatus, $returnStatus]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($user)->get('/orders')
                ->assertSee("<h1>{$ordersTitle}</h1>", false)
                ->assertSee($paymentStatus)
                ->assertSee($fulfillmentStatus)
                ->assertSee($returnStatus);
        }
    }

    public function test_each_supported_locale_translates_seller_orders_page(): void
    {
        $seller = User::create([
            'name' => 'Localized Seller',
            'account' => 'localized-seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $buyer = User::create([
            'name' => 'Localized Seller Buyer',
            'account' => 'localized-seller-buyer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Localized Seller Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'L202607120002',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'processing',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
            'shipping_status' => 'pending',
        ]);

        foreach ([
            'zh_TW' => ['商家訂單管理', '匯出 CSV', '買家', '待處理', '標記出貨'],
            'en' => ['Seller order management', 'Export CSV', 'Buyer', 'Pending', 'Mark as shipped'],
            'ja' => ['販売者注文管理', 'CSV をエクスポート', '購入者', '保留中', '発送済みにする'],
            'ko' => ['판매자 주문 관리', 'CSV 내보내기', '구매자', '대기 중', '배송 완료로 표시'],
            'es' => ['Gestión de pedidos del vendedor', 'Exportar CSV', 'Comprador', 'Pendiente', 'Marcar como enviado'],
        ] as $locale => [$title, $export, $buyerLabel, $shippingStatus, $ship]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($seller)->get('/seller/orders')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($export)
                ->assertSee($buyerLabel)
                ->assertSee($shippingStatus)
                ->assertSee($ship);
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
