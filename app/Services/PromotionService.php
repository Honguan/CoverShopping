<?php

namespace App\Services;

use App\Models\ShippingMethod;

class PromotionService
{
    public function calculateOrderDiscount(int $subtotal): int
    {
        $threshold = (int) config('commerce.order_discount_threshold', 0);
        $amount = (int) config('commerce.order_discount_amount', 0);

        if ($threshold <= 0 || $amount <= 0 || $subtotal < $threshold) {
            return 0;
        }

        return min($subtotal, $amount);
    }

    public function calculateShippingFee(?ShippingMethod $shippingMethod, int $subtotal): int
    {
        if (!$shippingMethod) {
            return 0;
        }

        $threshold = (int) config('commerce.free_shipping_threshold', 0);
        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0;
        }

        return $shippingMethod->fee;
    }

    public function freeShippingRemaining(int $subtotal): int
    {
        $threshold = (int) config('commerce.free_shipping_threshold', 0);

        return max(0, $threshold - $subtotal);
    }
}
