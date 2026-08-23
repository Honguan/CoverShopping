<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderCheckoutService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
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
            $this->assertSame('Only 3 in stock. Please update quantity.', $exception->getMessage());
            $this->assertSame(3, $product->fresh()->inventory);
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('cart_items', 2);
        } finally {
            $duplicate->delete();
            DB::statement('ALTER TABLE cart_items ADD UNIQUE INDEX cart_identity_unique (scope_key, product_id, variant_key)');
        }
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

    private function runConcurrently(array $commands): void
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

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        foreach ([$barrier, ...$readyFiles] as $path) {
            @unlink($path);
        }
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
