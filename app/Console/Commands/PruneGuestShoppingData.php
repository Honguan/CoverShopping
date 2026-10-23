<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneGuestShoppingData extends Command
{
    protected $signature = 'shopping:prune-guest-data';

    protected $description = 'Delete abandoned guest cart items and stale recently viewed products.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('commerce.guest_data_retention_days', 30));

        $cartItems = DB::table('cart_items')
            ->whereNull('user_id')
            ->where('updated_at', '<', $cutoff)
            ->delete();
        $views = DB::table('recently_viewed_products')
            ->where('viewed_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$cartItems} guest cart items and {$views} recently viewed rows.");

        return self::SUCCESS;
    }
}
