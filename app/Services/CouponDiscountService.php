<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use RuntimeException;

class CouponDiscountService
{
    public function findUsableCoupon(?string $code, int $subtotal, User $user): ?Coupon
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $coupon = Coupon::where('code', $code)->lockForUpdate()->first();

        if (!$coupon || !$coupon->is_active) {
            throw new RuntimeException('Coupon is not available.');
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new RuntimeException('Coupon is not active yet.');
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw new RuntimeException('Coupon has expired.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new RuntimeException('Coupon usage limit has been reached.');
        }

        if ($subtotal < $coupon->minimum_subtotal) {
            throw new RuntimeException('Order subtotal does not meet coupon minimum.');
        }

        if ($coupon->redemptions()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('Coupon has already been used by this user.');
        }

        return $coupon;
    }

    public function calculateDiscount(Coupon $coupon, int $subtotal): int
    {
        if ($coupon->type === 'percent') {
            return min($subtotal, (int) floor($subtotal * min(100, $coupon->value) / 100));
        }

        return min($subtotal, $coupon->value);
    }
}
