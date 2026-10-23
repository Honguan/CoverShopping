<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneGuestShoppingDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_stale_guest_data_is_pruned(): void
    {
        $user = User::create(['name' => 'Buyer', 'account' => 'prune-buyer', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
        $product = Product::create(['seller_id' => $user->id, 'name' => 'Kept', 'price' => 100, 'inventory' => 1, 'status' => 'active']);
        $stale = now()->subDays(31);

        $oldGuest = CartItem::create(['session_id' => 'old', 'product_id' => $product->id, 'quantity' => 1]);
        $freshGuest = CartItem::create(['session_id' => 'fresh', 'product_id' => $product->id, 'quantity' => 1]);
        $oldMember = CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        CartItem::whereKey([$oldGuest->id, $oldMember->id])->toBase()->update(['updated_at' => $stale]);
        DB::table('recently_viewed_products')->insert([
            ['session_id' => 'old', 'product_id' => $product->id, 'viewed_at' => $stale],
            ['session_id' => 'fresh', 'product_id' => $product->id, 'viewed_at' => now()],
        ]);

        $this->artisan('shopping:prune-guest-data')->assertSuccessful();

        $this->assertDatabaseMissing('cart_items', ['id' => $oldGuest->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $freshGuest->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $oldMember->id]);
        $this->assertDatabaseMissing('recently_viewed_products', ['session_id' => 'old']);
        $this->assertDatabaseHas('recently_viewed_products', ['session_id' => 'fresh']);
    }
}
