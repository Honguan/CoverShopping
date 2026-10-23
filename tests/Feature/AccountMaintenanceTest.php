<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_edit_updates_the_same_record_and_preserves_default_and_order_snapshot(): void
    {
        $buyer = $this->user();
        $address = Address::create($this->addressData() + ['user_id' => $buyer->id, 'is_default' => true]);
        $snapshot = $address->only(array_keys($this->addressData()));
        $order = Order::create([
            'number' => 'ADDRESS-EDIT', 'user_id' => $buyer->id, 'address_id' => $address->id,
            'subtotal' => 100, 'total' => 100, 'shipping_address_snapshot' => $snapshot,
        ]);
        $snapshot = $order->fresh()->shipping_address_snapshot;

        $this->actingAs($buyer)->patch('/addresses/'.$address->id, array_replace($this->addressData(), [
            'recipient_name' => 'New recipient', 'phone' => '0987654321', 'address_line' => 'New street',
            'is_default' => false,
        ]))->assertRedirect('/addresses');

        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseHas('addresses', [
            'id' => $address->id, 'recipient_name' => 'New recipient',
            'phone' => '0987654321', 'address_line' => 'New street', 'is_default' => true,
        ]);
        $this->assertSame($snapshot, $order->fresh()->shipping_address_snapshot);
        $this->get('/addresses')->assertOk()
            ->assertSee('action="'.route('addresses.update', $address).'"', false);
    }

    public function test_address_edit_cannot_promote_an_address_or_change_its_owner(): void
    {
        $buyer = $this->user();
        $default = Address::create($this->addressData() + ['user_id' => $buyer->id, 'is_default' => true]);
        $address = Address::create($this->addressData() + ['user_id' => $buyer->id, 'is_default' => false]);

        $this->actingAs($buyer)->patch('/addresses/'.$address->id, $this->addressData() + [
            'is_default' => true, 'user_id' => $this->user()->id,
        ])->assertRedirect('/addresses');

        $this->assertFalse($address->fresh()->is_default);
        $this->assertTrue($default->fresh()->is_default);
        $this->assertSame($buyer->id, $address->fresh()->user_id);
    }

    public function test_address_edit_requires_the_owner_and_valid_fields(): void
    {
        $owner = $this->user();
        $address = Address::create($this->addressData() + ['user_id' => $owner->id]);
        $this->patch('/addresses/'.$address->id, $this->addressData())->assertRedirect('/login');
        $this->actingAs($this->user())->patch('/addresses/'.$address->id, $this->addressData())->assertForbidden();
        $this->actingAs($owner)->from('/addresses')->patch('/addresses/'.$address->id, [
            'recipient_name' => '', 'phone' => '', 'city' => '', 'address_line' => '',
        ])->assertRedirect('/addresses')->assertSessionHasErrors(['recipient_name', 'phone', 'city', 'address_line']);
        $this->assertSame('Original recipient', $address->fresh()->recipient_name);
    }

    public function test_read_notification_remains_openable_and_preserves_first_read_time(): void
    {
        $buyer = $this->user();
        $notification = $this->notification($buyer);
        $this->travelTo(now()->startOfSecond());
        $this->actingAs($buyer)->patch('/notifications/'.$notification->id.'/read')->assertRedirect('/orders');
        $firstRead = $notification->fresh()->getRawOriginal('read_at');
        $this->travel(1)->hour();
        $this->patch('/notifications/'.$notification->id.'/read')->assertRedirect('/orders');

        $this->assertSame($firstRead, $notification->fresh()->getRawOriginal('read_at'));
        $this->get('/notifications')->assertOk()->assertSee('href="'.route('orders.index').'"', false)
            ->assertDontSee('action="'.route('notifications.read', $notification).'"', false);
        $this->travelBack();
    }

    public function test_notification_read_and_listing_are_scoped_to_the_owner(): void
    {
        $notification = $this->notification($this->user());
        $this->actingAs($this->user())->patch('/notifications/'.$notification->id.'/read')->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
        $this->get('/notifications')->assertOk()->assertDontSee($notification->title);
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Buyer', 'account' => 'account-'.uniqid(),
            'password' => 'password123', 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function addressData(): array
    {
        return [
            'recipient_name' => 'Original recipient', 'phone' => '0912345678',
            'postal_code' => '100', 'city' => 'Taipei', 'district' => 'Zhongzheng', 'address_line' => 'Old street',
        ];
    }

    private function notification(User $buyer): Notification
    {
        return Notification::create([
            'user_id' => $buyer->id, 'type' => 'order', 'title' => 'Saved order notification',
            'body' => 'Your order is available.', 'url' => route('orders.index'),
        ]);
    }
}
