<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use RuntimeException;

class ProductPricingService
{
    public function calculateUnitPrice(Product $product, ?ProductVariant $variant, ?User $user, int $quantity, bool $enforceMinimum = false): int
    {
        $basePrice = $product->price;

        if ($product->business_price !== null && $user && $user->canUseBusinessPricing()) {
            if ($enforceMinimum && $quantity < $product->business_min_quantity) {
                throw new RuntimeException(__('ui.business_minimum_quantity_message', ['quantity' => $product->business_min_quantity]));
            }

            $basePrice = $product->business_price;
        }

        return max(0, $basePrice + ($variant ? $variant->price_delta : 0));
    }

    public function detectSalesChannel(?User $user): string
    {
        return $user && $user->canUseBusinessPricing() ? 'b2b' : 'b2c';
    }
}
