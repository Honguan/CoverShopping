<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use RuntimeException;

class CouponDiscountService
{
    public function findUsableCoupon(?string $code, int $subtotal, User $user): ?Coupon
    {
        if (!$code) {
            return null;
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();

        if (!$coupon || !$coupon->is_active) {
            throw new RuntimeException('優惠券不存在或已停用');
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new RuntimeException('優惠券尚未開始');
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw new RuntimeException('優惠券已過期');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new RuntimeException('優惠券已達使用上限');
        }

        if ($subtotal < $coupon->minimum_subtotal) {
            throw new RuntimeException('訂單金額未達優惠券門檻');
        }

        if ($coupon->redemptions()->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('此優惠券已使用過');
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
