<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    /** @param int|null $expectedInventory 表單載入時的庫存；與目前不同代表期間有訂單異動，拒絕覆寫。 */
    public function setProductInventory(Product $product, User $user, int $inventory, string $reason, ?int $expectedInventory = null): Product
    {
        return DB::transaction(function () use ($product, $user, $inventory, $reason, $expectedInventory) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($expectedInventory !== null && $expectedInventory !== $lockedProduct->inventory) {
                throw ValidationException::withMessages(['inventory' => __('ui.product_inventory_changed')]);
            }

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
