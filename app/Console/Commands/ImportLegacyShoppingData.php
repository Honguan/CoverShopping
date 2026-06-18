<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportLegacyShoppingData extends Command
{
    protected $signature = 'legacy:import-shopping {--fresh : Stop when target tables already contain imported products}';

    protected $description = 'Import legacy CoverShopping users, products, and orders into the modern schema.';

    public function handle(): int
    {
        if ($this->option('fresh') && Product::whereNotNull('legacy_id')->exists()) {
            $this->error('Imported products already exist.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            $this->importUsers();
            $this->importAdmins();
            $this->importProducts();
            $this->importOrders();
        });

        $this->info('Legacy shopping data imported.');

        return self::SUCCESS;
    }

    private function importUsers(): void
    {
        DB::connection('legacy')->table('register')->orderBy('account')->get()->each(function ($legacyUser) {
            if (!$legacyUser->account) {
                return;
            }

            User::updateOrCreate(
                ['account' => $legacyUser->account],
                [
                    'name' => $legacyUser->name ?: $legacyUser->account,
                    'email' => $legacyUser->mail ?: null,
                    'password' => Hash::make($legacyUser->password ?: Str::random(32)),
                    'role' => 'customer',
                    'status' => $legacyUser->login ? ($legacyUser->switch === 'disable' ? 'pending' : 'active') : 'suspended',
                    'birthday' => $legacyUser->Birthday ?: null,
                ]
            );
        });
    }

    private function importAdmins(): void
    {
        DB::connection('legacy')->table('administrator')->orderBy('account')->get()->each(function ($legacyAdmin) {
            if (!$legacyAdmin->account) {
                return;
            }

            User::updateOrCreate(
                ['account' => $legacyAdmin->account],
                [
                    'name' => $legacyAdmin->account,
                    'email' => null,
                    'password' => Hash::make($legacyAdmin->password ?: Str::random(32)),
                    'role' => 'admin',
                    'status' => 'active',
                ]
            );
        });
    }

    private function importProducts(): void
    {
        $defaultSeller = User::firstOrCreate(
            ['account' => 'legacy-seller'],
            [
                'name' => 'Legacy Seller',
                'password' => Hash::make(Str::random(32)),
                'role' => 'seller',
                'status' => 'active',
            ]
        );

        DB::connection('legacy')->table('commodity')->orderBy('id')->get()->each(function ($legacyProduct) use ($defaultSeller) {
            $seller = User::where('account', trim((string) $legacyProduct->account))->first() ?: $defaultSeller;

            if ($seller->role === 'customer') {
                $seller->update(['role' => 'seller']);
            }

            $product = Product::updateOrCreate(
                ['legacy_id' => $legacyProduct->id],
                [
                    'seller_id' => $seller->id,
                    'name' => $legacyProduct->name,
                    'description' => $legacyProduct->content,
                    'price' => max(0, (int) round((float) $legacyProduct->price)),
                    'business_price' => null,
                    'business_min_quantity' => 1,
                    'inventory' => max(0, (int) $legacyProduct->inventory),
                    'status' => 'active',
                ]
            );

            if ($legacyProduct->image) {
                $product->images()->updateOrCreate(
                    ['path' => $this->normalizeLegacyImagePath($legacyProduct->image)],
                    ['is_primary' => true, 'sort_order' => 0]
                );
            }
        });
    }

    private function importOrders(): void
    {
        DB::connection('legacy')
            ->table('orders')
            ->orderBy('time')
            ->get()
            ->groupBy('no')
            ->each(function ($legacyItems, string $legacyNumber) {
                $first = $legacyItems->first();
                $user = User::where('account', $first->name)->first();

                if (!$user) {
                    return;
                }

                $subtotal = 0;
                $snapshots = [];

                foreach ($legacyItems as $legacyItem) {
                    $product = Product::where('legacy_id', $legacyItem->id)->first();

                    if (!$product) {
                        continue;
                    }

                    $quantity = max(1, (int) $legacyItem->quantity);
                    $itemSubtotal = $product->price * $quantity;
                    $subtotal += $itemSubtotal;
                    $snapshots[] = [$product, $quantity, $itemSubtotal, $legacyItem];
                }

                if (!$snapshots) {
                    return;
                }

                $order = Order::updateOrCreate(
                    ['number' => 'LEGACY-' . $legacyNumber],
                    [
                        'user_id' => $user->id,
                        'subtotal' => $subtotal,
                        'shipping_fee' => max(0, (int) $first->freight),
                        'total' => $subtotal + max(0, (int) $first->freight),
                        'payment_status' => $first->payment ? 'paid' : 'unpaid',
                        'fulfillment_status' => $first->Shipping ? 'shipped' : 'pending',
                        'created_at' => $first->time,
                        'updated_at' => $first->time,
                    ]
                );

                foreach ($snapshots as [$product, $quantity, $itemSubtotal, $legacyItem]) {
                    $order->items()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'seller_id' => $product->seller_id,
                            'product_name' => $product->name,
                            'unit_price' => $product->price,
                            'quantity' => $quantity,
                            'subtotal' => $itemSubtotal,
                            'shipping_status' => $legacyItem->Shipping ? 'shipped' : 'pending',
                            'shipped_at' => $legacyItem->Shipping ? $legacyItem->time : null,
                        ]
                    );
                }
            });
    }

    private function normalizeLegacyImagePath(string $image): string
    {
        $image = str_replace('\\', '/', trim($image));

        if (str_starts_with($image, './images/')) {
            return '/CoverShopping/public/images/' . basename($image);
        }

        if (str_starts_with($image, '/public/images/')) {
            return '/CoverShopping' . $image;
        }

        return $image;
    }
}
