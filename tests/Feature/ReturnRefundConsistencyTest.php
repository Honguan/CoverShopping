<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReturnRefundConsistencyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('refundEndpoints')]
    public function test_refund_endpoints_synchronize_statuses_once_without_restocking(string $endpoint, string $fulfillment): void
    {
        [$admin, , $order, $returnRequest, $product] = $this->createReceivedReturn($fulfillment);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->refund($admin, $order, $returnRequest, $endpoint)
                ->assertRedirect('/admin/dashboard')
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->fresh()->return_status);
        $this->assertSame($fulfillment, $order->fresh()->fulfillment_status);
        $this->assertSame('refunded', $returnRequest->fresh()->status);
        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertEquals($returnRequest->inventory_restocked_at, $returnRequest->fresh()->inventory_restocked_at);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(1, AuditLog::where('action', 'admin.order.payment_updated')->count());
        $this->assertSame(1, AuditLog::where('action', 'admin.return.updated')->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.return.updated',
            'auditable_type' => ReturnRequest::class,
            'auditable_id' => $returnRequest->id,
            'user_id' => $admin->id,
        ]);
    }

    public static function refundEndpoints(): array
    {
        return [
            'payment shipped' => ['payment', 'shipped'],
            'payment completed' => ['payment', 'completed'],
            'return shipped' => ['return', 'shipped'],
            'return completed' => ['return', 'completed'],
        ];
    }

    #[DataProvider('legacyEndpoints')]
    public function test_legacy_refunded_payment_repairs_return_without_rewriting_payment_audit(string $endpoint): void
    {
        [$admin, , $order, $returnRequest, $product] = $this->createReceivedReturn();
        $order->update(['payment_status' => 'refunded']);
        $paymentAudit = AuditLog::create([
            'action' => 'admin.order.payment_updated',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'payload' => ['payment_status' => 'refunded'],
        ]);
        $paymentAudit->refresh();

        $this->refund($admin, $order, $returnRequest, $endpoint)->assertSessionHasNoErrors();
        $this->refund($admin, $order, $returnRequest, $endpoint)->assertSessionHasNoErrors();

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->fresh()->return_status);
        $this->assertSame('refunded', $returnRequest->fresh()->status);
        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(1, AuditLog::where('action', 'admin.order.payment_updated')->count());
        $this->assertEquals($paymentAudit->getAttributes(), $paymentAudit->fresh()->getAttributes());
        $this->assertSame(1, AuditLog::where('action', 'admin.return.updated')->count());
    }

    public static function legacyEndpoints(): array
    {
        return [['payment'], ['return']];
    }

    #[DataProvider('legacyEndpoints')]
    public function test_non_admin_cannot_refund_a_received_return(string $endpoint): void
    {
        [, $buyer, $order, $returnRequest, $product] = $this->createReceivedReturn();

        $this->refund($buyer, $order, $returnRequest, $endpoint)->assertForbidden();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('received', $order->fresh()->return_status);
        $this->assertSame('received', $returnRequest->fresh()->status);
        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('invalidRefunds')]
    public function test_refund_before_return_receipt_leaves_all_state_unchanged(string $endpoint, string $orderStatus, string $returnStatus): void
    {
        [$admin, , $order, $returnRequest, $product] = $this->createReceivedReturn();
        $order->update(['return_status' => $orderStatus]);
        $returnRequest->update(['status' => $returnStatus]);

        $this->refund($admin, $order, $returnRequest, $endpoint)
            ->assertSessionHasErrors($endpoint === 'payment' ? 'payment_status' : 'return');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame($orderStatus, $order->fresh()->return_status);
        $this->assertSame($returnStatus, $returnRequest->fresh()->status);
        $this->assertSame(2, $product->fresh()->inventory);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidRefunds(): array
    {
        return [
            'payment before receipt' => ['payment', 'approved', 'approved'],
            'return before receipt' => ['return', 'approved', 'approved'],
            'payment inconsistent return row' => ['payment', 'received', 'approved'],
            'return inconsistent order' => ['return', 'approved', 'received'],
        ];
    }

    private function refund(User $user, Order $order, ReturnRequest $returnRequest, string $endpoint): TestResponse
    {
        return $this->actingAs($user)->patch(
            $endpoint === 'payment' ? "/admin/orders/{$order->id}/payment" : "/admin/returns/{$returnRequest->id}",
            $endpoint === 'payment' ? ['payment_status' => 'refunded'] : ['status' => 'refunded'],
        );
    }

    private function createReceivedReturn(string $fulfillment = 'completed'): array
    {
        $seller = User::create(['name' => 'Seller', 'account' => 'refund-seller', 'password' => 'password', 'role' => 'seller', 'status' => 'active']);
        $buyer = User::create(['name' => 'Buyer', 'account' => 'refund-buyer', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'account' => 'refund-admin', 'password' => 'password', 'role' => 'admin', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Returned Product',
            'price' => 100,
            'inventory' => 2,
            'status' => 'active',
        ]);
        $order = Order::create([
            'number' => 'RETURN-REFUND-1',
            'user_id' => $buyer->id,
            'subtotal' => 200,
            'total' => 200,
            'payment_status' => 'paid',
            'fulfillment_status' => $fulfillment,
            'return_status' => 'received',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);
        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'reason' => 'Wrong size',
            'status' => 'received',
            'inventory_restocked_at' => now(),
        ]);
        InventoryMovement::create([
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'reason' => 'return_received',
            'quantity_delta' => 2,
            'inventory_after' => 2,
            'reference_type' => OrderItem::class,
            'reference_id' => $item->id,
        ]);

        return [$admin, $buyer, $order, $returnRequest, $product];
    }
}
