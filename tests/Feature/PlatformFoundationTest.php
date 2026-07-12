<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CartItem;
use App\Models\BusinessProfile;
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

    public function test_trusted_proxy_headers_produce_https_urls(): void
    {
        $this->get('/', [
            'HTTP_HOST' => 'shop.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->assertOk();

        $this->assertTrue(request()->isSecure());
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

    public function test_supported_locales_have_matching_ui_keys(): void
    {
        $source = require lang_path('zh_TW/ui.php');

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $translations = require lang_path("{$locale}/ui.php");

            $this->assertSame(array_keys($source), array_keys($translations));
        }
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

    public function test_each_supported_locale_translates_seller_products_page(): void
    {
        $seller = User::create([
            'name' => 'Localized Product Seller',
            'account' => 'localized-product-seller',
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        Product::create([
            'seller_id' => $seller->id,
            'name' => 'Localized Product',
            'price' => 100,
            'inventory' => 1,
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => ['商家商品管理', '低庫存提醒', '新增商品', '送出審核', '已上架', '商品問答', '目前沒有待處理問答。'],
            'en' => ['Seller product management', 'Low stock alert', 'Add product', 'Submit for review', 'Active', 'Product questions', 'No pending product questions.'],
            'ja' => ['販売者商品管理', '在庫不足アラート', '商品を追加', '審査を申請', '販売中', '商品への質問', '未対応の質問はありません。'],
            'ko' => ['판매자 상품 관리', '재고 부족 알림', '상품 추가', '검토 요청', '판매 중', '상품 문의', '처리 대기 중인 문의가 없습니다.'],
            'es' => ['Gestión de productos del vendedor', 'Alerta de bajo inventario', 'Añadir producto', 'Enviar a revisión', 'Activo', 'Preguntas del producto', 'No hay preguntas pendientes sobre productos.'],
        ] as $locale => [$title, $lowStock, $create, $submit, $status, $questions, $noQuestions]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($seller)->get('/seller/products')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($lowStock)
                ->assertSee($create)
                ->assertSee($submit)
                ->assertSee($status)
                ->assertSee($questions)
                ->assertSee($noQuestions);
        }
    }

    public function test_each_supported_locale_translates_admin_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Localized Admin',
            'account' => 'localized-admin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => ['管理後台', '會員', '企業會員審核', '商品審核', '付款狀態', '優惠券', '退貨申請', '配送方式', '啟用', '管理員'],
            'en' => ['Admin dashboard', 'Members', 'Business profile review', 'Product approval', 'Payment status', 'Coupon', 'Return requests', 'Shipping method', 'Active', 'Administrator'],
            'ja' => ['管理画面', '会員', '法人アカウント審査', '商品審査', '支払い状況', 'クーポン', '返品申請', '配送方法', '有効', '管理者'],
            'ko' => ['관리자 페이지', '회원', '기업 회원 검토', '상품 검토', '결제 상태', '쿠폰', '반품 요청', '배송 방법', '활성', '관리자'],
            'es' => ['Panel de administración', 'Miembros', 'Revisión de perfil empresarial', 'Aprobación de productos', 'Estado del pago', 'Cupón', 'Solicitudes de devolución', 'Método de envío', 'Activo', 'Administrador'],
        ] as $locale => [$title, $members, $businessProfiles, $products, $paymentStatus, $coupon, $returns, $shipping, $active, $adminRole]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($admin)->get('/admin/dashboard')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($members)
                ->assertSee($businessProfiles)
                ->assertSee($products)
                ->assertSee($paymentStatus)
                ->assertSee($coupon)
                ->assertSee($returns)
                ->assertSee($shipping)
                ->assertSee($active)
                ->assertSee($adminRole);
        }
    }

    public function test_each_supported_locale_translates_business_profile_page(): void
    {
        $user = User::create([
            'name' => 'Localized Business User',
            'account' => 'localized-business-user',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        BusinessProfile::create([
            'user_id' => $user->id,
            'company_name' => 'Localized Business',
            'tax_id' => '12345678',
            'contact_name' => 'Business Contact',
            'contact_phone' => '0911000000',
            'status' => 'pending',
        ]);

        foreach ([
            'zh_TW' => ['企業會員資料', '審核狀態', '公司名稱', '聯絡人', '聯絡電話', '帳務 Email', '待審核'],
            'en' => ['Business account details', 'Review status', 'Company name', 'Contact name', 'Contact phone', 'Billing email', 'Pending'],
            'ja' => ['法人アカウント情報', '審査状況', '会社名', '担当者名', '連絡先電話番号', '請求先メールアドレス', '審査待ち'],
            'ko' => ['기업 회원 정보', '검토 상태', '회사명', '담당자명', '연락처 전화번호', '청구 이메일', '검토 대기'],
            'es' => ['Datos de la cuenta empresarial', 'Estado de revisión', 'Nombre de la empresa', 'Nombre de contacto', 'Teléfono de contacto', 'Correo de facturación', 'Pendiente'],
        ] as $locale => [$title, $reviewStatus, $company, $contactName, $contactPhone, $billingEmail, $status]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($user)->get('/business-profile')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($reviewStatus)
                ->assertSee($company)
                ->assertSee($contactName)
                ->assertSee($contactPhone)
                ->assertSee($billingEmail)
                ->assertSee($status);
        }
    }

    public function test_each_supported_locale_translates_addresses_page(): void
    {
        $user = User::create([
            'name' => 'Localized Address User',
            'account' => 'localized-address-user',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => ['地址簿', '新增收件地址', '收件人', '電話', '郵遞區號', '城市', '區域', '詳細地址', '設為預設地址', '新增地址', '尚未建立收件地址。'],
            'en' => ['Addresses', 'Add shipping address', 'Recipient name', 'Phone', 'Postal code', 'City', 'District', 'Address line', 'Set as default address', 'Add address', 'No shipping addresses yet.'],
            'ja' => ['住所', '配送先住所を追加', '受取人名', '電話番号', '郵便番号', '市区町村', '地区', '住所', '既定の住所に設定', '住所を追加', '配送先住所はまだありません。'],
            'ko' => ['주소록', '배송지 주소 추가', '수령인 이름', '전화번호', '우편번호', '도시', '지역', '상세 주소', '기본 주소로 설정', '주소 추가', '배송지 주소가 없습니다.'],
            'es' => ['Direcciones', 'Añadir dirección de envío', 'Nombre del destinatario', 'Teléfono', 'Código postal', 'Ciudad', 'Distrito', 'Dirección', 'Establecer como dirección predeterminada', 'Añadir dirección', 'Aún no hay direcciones de envío.'],
        ] as $locale => [$title, $addShipping, $recipient, $phone, $postalCode, $city, $district, $addressLine, $default, $addAddress, $empty]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($user)->get('/addresses')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($addShipping)
                ->assertSee($recipient)
                ->assertSee($phone)
                ->assertSee($postalCode)
                ->assertSee($city)
                ->assertSee($district)
                ->assertSee($addressLine)
                ->assertSee($default)
                ->assertSee($addAddress)
                ->assertSee($empty);
        }
    }

    public function test_each_supported_locale_translates_address_saved_message(): void
    {
        $user = User::create([
            'name' => 'Localized Address Message User',
            'account' => 'localized-address-message-user',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => '地址已儲存。',
            'en' => 'Address saved.',
            'ja' => '住所を保存しました。',
            'ko' => '주소를 저장했습니다.',
            'es' => 'Dirección guardada.',
        ] as $locale => $message) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($user)->post('/addresses', [
                'recipient_name' => "Recipient {$locale}",
                'phone' => '0911000000',
                'city' => 'Taipei',
                'address_line' => 'No. 1',
            ])
                ->assertRedirect('/addresses')
                ->assertSessionHas('status', $message);
        }
    }

    public function test_each_supported_locale_translates_notifications_page(): void
    {
        $user = User::create([
            'name' => 'Localized Notifications User',
            'account' => 'localized-notifications-user',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        foreach ([
            'zh_TW' => ['通知中心', '目前沒有通知。'],
            'en' => ['Notification center', 'No notifications yet.'],
            'ja' => ['通知センター', '通知はまだありません。'],
            'ko' => ['알림 센터', '알림이 없습니다.'],
            'es' => ['Centro de notificaciones', 'Aún no hay notificaciones.'],
        ] as $locale => [$title, $empty]) {
            $this->get("/locale/{$locale}")->assertRedirect('/');
            $this->actingAs($user)->get('/notifications')
                ->assertSee("<h1>{$title}</h1>", false)
                ->assertSee($empty);
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
