<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminDashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reach_older_records_in_every_dashboard_list(): void
    {
        [$admin, $records] = $this->createDashboardRecords();
        $firstPage = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $pages = [];

        foreach ($records as $name => [$pageName, $ids]) {
            $paginator = $firstPage->viewData($name);
            $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
            $this->assertSame(10, $paginator->perPage());
            $this->assertSame(array_slice($ids, 0, 10), $paginator->getCollection()->pluck('id')->all());
            $firstPage->assertSee('href="'.e($paginator->url(2)).'"', false);
            $pages[$pageName] = 2;
        }

        $secondPage = $this->get('/admin/dashboard?'.http_build_query($pages))->assertOk();

        foreach ($records as $name => [$pageName, $ids]) {
            $paginator = $secondPage->viewData($name);
            $this->assertSame(2, $paginator->currentPage());
            $this->assertSame(array_slice($ids, 10), $paginator->getCollection()->pluck('id')->all());
        }

        $returnRequest = $secondPage->viewData('returnRequests')->first();
        $this->assertTrue($returnRequest->relationLoaded('order'));
        $this->assertTrue($returnRequest->order->relationLoaded('user'));
        $secondPage->assertSee(route('admin.users.status', $admin));
        $secondPage->assertSee('Product 1');
        $secondPage->assertSee('ORDER-1');
        $secondPage->assertSee('COUPON1');
        $secondPage->assertSee('Company 1');
        $secondPage->assertSee('Shipping 11');
    }

    public function test_dashboard_page_parameters_are_independent_and_preserved_in_links(): void
    {
        [$admin, $records] = $this->createDashboardRecords();
        $response = $this->actingAs($admin)
            ->get('/admin/dashboard?users_page=2&page=3&locale=en')->assertOk();

        foreach ($records as $name => [$pageName, $ids]) {
            $paginator = $response->viewData($name);
            $this->assertSame($name === 'users' ? 2 : 1, $paginator->currentPage());
            $response->assertSee('href="'.e($paginator->url($name === 'users' ? 1 : 2)).'"', false);
            parse_str(parse_url($paginator->url(2), PHP_URL_QUERY), $query);
            $this->assertSame('2', $query['users_page']);
            $this->assertSame('en', $query['locale']);
        }
    }

    public function test_dashboard_pagination_does_not_bypass_access_control(): void
    {
        $this->get('/admin/dashboard?users_page=2')->assertRedirect('/login');

        foreach (['customer', 'seller'] as $role) {
            $user = User::create([
                'name' => $role,
                'account' => $role,
                'password' => 'password',
                'role' => $role,
                'status' => 'active',
            ]);

            $this->actingAs($user)->get('/admin/dashboard?users_page=2')->assertForbidden();
        }
    }

    private function createDashboardRecords(): array
    {
        $this->travelTo(now()->startOfSecond());
        $admin = User::create([
            'name' => 'Admin',
            'account' => 'admin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $records = [
            'users' => ['users_page', [$admin->id]],
            'products' => ['products_page', []],
            'orders' => ['orders_page', []],
            'coupons' => ['coupons_page', []],
            'shippingMethods' => ['shipping_methods_page', []],
            'returnRequests' => ['returns_page', []],
            'businessProfiles' => ['business_profiles_page', []],
        ];

        for ($i = 1; $i <= 11; $i++) {
            $user = User::create([
                'name' => 'Customer '.$i,
                'account' => 'customer-'.$i,
                'password' => 'password',
                'role' => 'customer',
                'status' => 'active',
            ]);
            $records['users'][1][] = $user->id;
            $records['products'][1][] = Product::create([
                'seller_id' => $admin->id,
                'name' => 'Product '.$i,
                'price' => 100,
            ])->id;
            $order = Order::create([
                'number' => 'ORDER-'.$i,
                'user_id' => $user->id,
                'subtotal' => 100,
                'total' => 100,
            ]);
            $records['orders'][1][] = $order->id;
            $records['coupons'][1][] = Coupon::create([
                'code' => 'COUPON'.$i,
                'name' => 'Coupon '.$i,
                'type' => 'fixed',
                'value' => 10,
            ])->id;
            $records['shippingMethods'][1][] = ShippingMethod::create([
                'name' => 'Shipping '.$i,
                'sort_order' => $i === 11 ? 1 : 0,
            ])->id;
            $records['returnRequests'][1][] = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason' => 'Return '.$i,
            ])->id;
            $records['businessProfiles'][1][] = BusinessProfile::create([
                'user_id' => $user->id,
                'company_name' => 'Company '.$i,
                'tax_id' => 'TAX'.$i,
                'contact_name' => $user->name,
                'contact_phone' => '0911000000',
            ])->id;
        }

        foreach ($records as $name => [$pageName, $ids]) {
            $records[$name][1] = $name === 'shippingMethods'
                ? [...array_reverse(array_slice($ids, 0, 10)), $ids[10]]
                : array_reverse($ids);
        }

        return [$admin, $records];
    }
}
