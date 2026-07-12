<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function setProductInventory(Product $product, User $user, int $inventory, string $reason): Product
    {
        return DB::transaction(function () use ($product, $user, $inventory, $reason) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $quantityDelta = $inventory - $lockedProduct->inventory;

            if ($quantityDelta === 0) {
                return $lockedProduct;
            }

            $lockedProduct->update(['inventory' => $inventory]);

            InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'quantity_delta' => $quantityDelta,
                'inventory_after' => $inventory,
                'reference_type' => Product::class,
                'reference_id' => $lockedProduct->id,
            ]);

            return $lockedProduct;
        });
    }
}
