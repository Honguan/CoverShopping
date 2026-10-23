<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Queries\ProductCatalogQuery;
use App\Services\OrderCheckoutService;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlCartConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_mysql_enforces_cart_owner_and_null_variant_uniqueness(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();

        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->assertQueryRejected(fn () => CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]));

        CartItem::create(['session_id' => 'mysql-session', 'product_id' => $product->id, 'quantity' => 1]);
        $this->assertQueryRejected(fn () => CartItem::create(['session_id' => 'mysql-session', 'product_id' => $product->id, 'quantity' => 1]));
        $this->assertQueryRejected(fn () => CartItem::create(['product_id' => $product->id, 'quantity' => 1]));
        $this->assertQueryRejected(fn () => CartItem::create(['user_id' => $user->id, 'session_id' => 'both', 'product_id' => $product->id, 'quantity' => 1]));

        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_mysql_concurrent_adds_produce_one_row_with_the_total_quantity(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $this->runConcurrently([
            [
                PHP_BINARY,
                base_path('tests/Support/cart_add_worker.php'),
                (string) $user->id,
                (string) $product->id,
                '2',
            ],
            [
                PHP_BINARY,
                base_path('tests/Support/cart_add_worker.php'),
                (string) $user->id,
                (string) $product->id,
                '2',
            ],
        ]);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 4,
        ]);

    }

    public function test_mysql_concurrent_guest_merges_atomically_combine_quantities(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        CartItem::create(['session_id' => 'merge-a', 'product_id' => $product->id, 'quantity' => 2]);
        CartItem::create(['session_id' => 'merge-b', 'product_id' => $product->id, 'quantity' => 3]);

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/cart_merge_worker.php'), (string) $user->id, 'merge-a'],
            [PHP_BINARY, base_path('tests/Support/cart_merge_worker.php'), (string) $user->id, 'merge-b'],
        ]);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'session_id' => null,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_mysql_concurrent_default_address_changes_leave_one_default(): void
    {
        $this->requireMySql();
        [, $user] = $this->createProductAndUser();
        $first = $this->createAddress($user, 'First');
        $second = $this->createAddress($user, 'Second');

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/address_default_worker.php'), (string) $user->id, (string) $first->id],
            [PHP_BINARY, base_path('tests/Support/address_default_worker.php'), (string) $user->id, (string) $second->id],
        ]);

        $this->assertSame(1, Address::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_mysql_catalog_search_uses_fulltext_and_applies_catalog_filters(): void
    {
        $this->requireMySql();
        [$product] = $this->createProductAndUser();
        $category = Category::create(['name' => 'Indexed', 'slug' => 'indexed', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'name' => 'Indexed catalogtoken one', 'price' => 150]);
        $second = Product::create([
            'seller_id' => $product->seller_id,
            'category_id' => $category->id,
            'name' => 'Indexed catalogtoken two',
            'price' => 250,
            'inventory' => 1,
            'status' => 'active',
        ]);
        Product::create(['seller_id' => $product->seller_id, 'category_id' => $category->id, 'name' => 'Indexed catalogtoken inactive', 'price' => 200, 'inventory' => 1, 'status' => 'archived']);
        Product::create(['seller_id' => $product->seller_id, 'category_id' => $otherCategory->id, 'name' => 'Indexed catalogtoken other', 'price' => 200, 'inventory' => 1, 'status' => 'active']);
        Product::create(['seller_id' => $product->seller_id, 'category_id' => $category->id, 'name' => 'Indexed catalogtoken expensive', 'price' => 600, 'inventory' => 1, 'status' => 'active']);
        config(['scout.driver' => 'database']);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        $results = app(ProductCatalogQuery::class)->paginate(Request::create('/products', 'GET', [
            'q' => 'catalogtoken',
            'category' => 'indexed',
            'min_price' => 100,
            'max_price' => 500,
            'sort' => 'price_desc',
        ]));

        $this->assertSame([$second->id, $product->id], collect($results->items())->pluck('id')->all());
        $searchQuery = collect($queries)->first(fn (QueryExecuted $query) => str_contains(strtolower($query->sql), 'match (') && str_contains(strtolower($query->sql), 'select *'));
        $this->assertInstanceOf(QueryExecuted::class, $searchQuery);
        $plan = DB::select('EXPLAIN '.$searchQuery->toRawSql());
        $this->assertTrue(collect($plan)->contains(fn (object $row) => strtolower((string) ($row->type ?? '')) === 'fulltext'));
        $this->assertStringNotContainsString(' like ', strtolower($searchQuery->sql));
    }

    public function test_checkout_validates_the_total_of_legacy_duplicate_rows(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $product->update(['inventory' => 3]);
        DB::statement('ALTER TABLE cart_items DROP INDEX cart_identity_unique');
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);
        $duplicate = CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        try {
            app(OrderCheckoutService::class)->createOrderFromCart($user);
            $this->fail('Checkout should reject the cumulative quantity.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('ui.stock_quantity_available', ['quantity' => 3]), $exception->getMessage());
            $this->assertSame(3, $product->fresh()->inventory);
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('cart_items', 2);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('coupon_redemptions', 0);
        } finally {
            $duplicate->delete();
            DB::statement('ALTER TABLE cart_items ADD UNIQUE INDEX cart_identity_unique (scope_key, product_id, variant_key)');
        }
    }

    public function test_mysql_concurrent_checkouts_create_one_order_and_inventory_movement(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $product->update(['inventory' => 2]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/checkout_worker.php'), (string) $user->id, '-'],
            [PHP_BINARY, base_path('tests/Support/checkout_worker.php'), (string) $user->id, '-'],
        ], 1);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(0, $product->fresh()->inventory);
    }

    public function test_mysql_concurrent_return_requests_create_one_request(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $order = Order::create([
            'number' => 'MR-'.uniqid(),
            'user_id' => $user->id,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/return_request_worker.php'), (string) $user->id, (string) $order->id],
            [PHP_BINARY, base_path('tests/Support/return_request_worker.php'), (string) $user->id, (string) $order->id],
        ], 1);

        $this->assertDatabaseCount('return_requests', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'return_status' => 'requested']);
        $this->assertSame(10, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_mysql_concurrent_reviews_preserve_one_review_and_one_audit_log(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $order = Order::create([
            'number' => 'REV-'.uniqid(),
            'user_id' => $user->id,
            'subtotal' => 100,
            'total' => 100,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $outputs = $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/review_create_worker.php'), (string) $user->id, (string) $product->id, (string) $item->id, '5', 'First review'],
            [PHP_BINARY, base_path('tests/Support/review_create_worker.php'), (string) $user->id, (string) $product->id, (string) $item->id, '1', 'Second review'],
        ]);

        $reviews = array_map(fn (string $output) => json_decode($output, true, flags: JSON_THROW_ON_ERROR), $outputs);
        $this->assertSame($reviews[0], $reviews[1]);
        $this->assertContains($reviews[0], [[5, 'First review'], [1, 'Second review']]);
        $this->assertDatabaseCount('product_reviews', 1);
        $review = ProductReview::query()->firstOrFail();
        $this->assertSame($reviews[0], [$review->rating, $review->content]);
        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_item_id' => $item->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product.review.created',
            'auditable_type' => ProductReview::class,
            'auditable_id' => $review->id,
        ]);
    }

    public function test_mysql_concurrent_refund_endpoints_synchronize_statuses_without_restocking(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $admin = User::create([
            'name' => 'Admin',
            'account' => 'mysql-refund-admin-'.uniqid(),
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'REF-'.uniqid(),
            'user_id' => $user->id,
            'subtotal' => 200,
            'total' => 200,
            'payment_status' => 'paid',
            'fulfillment_status' => 'completed',
            'return_status' => 'received',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);
        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => 'Concurrent refund',
            'status' => 'received',
            'inventory_restocked_at' => now(),
        ]);
        InventoryMovement::create([
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'reason' => 'return_received',
            'quantity_delta' => 2,
            'inventory_after' => $product->inventory,
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
        ]);

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/return_refund_worker.php'), (string) $admin->id, (string) $returnRequest->id, 'payment'],
            [PHP_BINARY, base_path('tests/Support/return_refund_worker.php'), (string) $admin->id, (string) $returnRequest->id, 'return'],
        ]);

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->fresh()->return_status);
        $this->assertSame('completed', $order->fresh()->fulfillment_status);
        $this->assertSame('refunded', $returnRequest->fresh()->status);
        $this->assertEquals($returnRequest->inventory_restocked_at, $returnRequest->fresh()->inventory_restocked_at);
        $this->assertSame(10, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.order.payment_updated')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.return.updated')->count());
    }

    public function test_mysql_concurrent_coupon_checkouts_honor_the_usage_limit(): void
    {
        $this->requireMySql();
        [$firstProduct, $firstUser] = $this->createProductAndUser();
        $secondUser = User::create([
            'name' => 'Second Buyer',
            'account' => 'mysql-second-buyer-'.uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $secondProduct = Product::create([
            'seller_id' => $firstProduct->seller_id,
            'name' => 'Second Concurrent Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);
        $coupon = Coupon::create([
            'code' => 'MYSQL-ONE-'.strtoupper(uniqid()),
            'name' => 'One use',
            'type' => 'fixed',
            'value' => 10,
            'usage_limit' => 1,
            'is_active' => true,
        ]);
        CartItem::create(['user_id' => $firstUser->id, 'product_id' => $firstProduct->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $secondUser->id, 'product_id' => $secondProduct->id, 'quantity' => 1]);

        $this->runConcurrently([
            [PHP_BINARY, base_path('tests/Support/checkout_worker.php'), (string) $firstUser->id, $coupon->code],
            [PHP_BINARY, base_path('tests/Support/checkout_worker.php'), (string) $secondUser->id, $coupon->code],
        ], 1);

        $order = Order::query()->firstOrFail();
        $losingUser = $order->user_id === $firstUser->id ? $secondUser : $firstUser;
        $losingProduct = $order->user_id === $firstUser->id ? $secondProduct : $firstProduct;

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('coupon_redemptions', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'used_count' => 1]);
        $this->assertDatabaseMissing('orders', ['user_id' => $losingUser->id]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $losingUser->id, 'product_id' => $losingProduct->id]);
        $this->assertDatabaseMissing('inventory_movements', ['user_id' => $losingUser->id]);
        $this->assertSame(10, $losingProduct->fresh()->inventory);
    }

    public function test_mysql_checkout_rolls_back_all_writes_after_a_movement_failure(): void
    {
        $this->requireMySql();
        [$product, $user] = $this->createProductAndUser();
        $coupon = Coupon::create([
            'code' => 'MYSQL-ROLLBACK-'.strtoupper(uniqid()),
            'name' => 'Rollback',
            'type' => 'fixed',
            'value' => 10,
            'usage_limit' => 1,
            'is_active' => true,
        ]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT force_checkout_rollback CHECK (quantity_delta >= 0)');

        try {
            app(OrderCheckoutService::class)->createOrderFromCart($user, couponCode: $coupon->code);
            $this->fail('Checkout should fail while writing the inventory movement.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        } finally {
            DB::statement('ALTER TABLE inventory_movements DROP CHECK force_checkout_rollback');
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'used_count' => 0]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertSame(10, $product->fresh()->inventory);
    }

    private function requireMySql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This test verifies MySQL locking and nullable unique behavior.');
        }
    }

    private function assertQueryRejected(Closure $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the database to reject an invalid cart identity.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function temporaryPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'cart-test-'.bin2hex(random_bytes(8));
    }

    private function runConcurrently(array $commands, int $expectedFailures = 0): array
    {
        $barrier = $this->temporaryPath();
        $readyFiles = array_map(fn () => $this->temporaryPath(), $commands);
        $processes = array_map(
            fn (array $command, int $index) => new Process([...$command, $readyFiles[$index], $barrier]),
            $commands,
            array_keys($commands),
        );

        foreach ($processes as $process) {
            $process->start();
        }

        $deadline = microtime(true) + 10;
        while (count(array_filter($readyFiles, 'file_exists')) !== count($readyFiles) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        touch($barrier);

        $failures = [];
        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                $failures[] = $process->getErrorOutput();
            }
        }

        $this->assertCount($expectedFailures, $failures, implode(PHP_EOL, $failures));

        foreach ([$barrier, ...$readyFiles] as $path) {
            @unlink($path);
        }

        return array_map(fn (Process $process) => $process->getOutput(), $processes);
    }

    private function createProductAndUser(): array
    {
        $seller = User::create([
            'name' => 'Seller',
            'account' => 'mysql-seller-'.uniqid(),
            'password' => 'password',
            'role' => 'seller',
            'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Buyer',
            'account' => 'mysql-buyer-'.uniqid(),
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Concurrent Product',
            'price' => 100,
            'inventory' => 10,
            'status' => 'active',
        ]);

        return [$product, $user];
    }

    private function createAddress(User $user, string $recipient): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'recipient_name' => $recipient,
            'phone' => '0911000000',
            'city' => 'Taipei',
            'address_line' => 'No. 1',
        ]);
    }
}
