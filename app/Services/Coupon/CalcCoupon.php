<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\DraftCheckout;
use Illuminate\Support\Carbon;

class CalcCoupon
{
    public function applyCoupon(int $couponId, DraftCheckout $draft, ?int $userId)
    {
        $coupon = Coupon::where('id', $couponId)->first();
        $totalPrice = $draft->total_price;

        if (!$coupon) {
            throw new \RuntimeException('Coupon not found.');
        }

        if ($coupon->status !== 'active') {
            throw new \RuntimeException('Coupon is not active.');
        }
        $now = Carbon::now();
        if ($coupon->start_at && $now->lt($coupon->start_at)) {
            throw new \Exception('Mã giảm giá chưa đến ngày áp dụng.');
        }
        if ($coupon->end_at && $now->gt($coupon->end_at)) {
            throw new \Exception('Mã giảm giá đã hết hạn sử dụng.');
        }

        $discountAmount = 0;

        if ($coupon->discount_type === 'percentage') {
            $discountAmount = ($coupon->discount_value / 100) * $totalPrice;
        } elseif ($coupon->discount_type === 'fixed_amount') {
            $discountAmount = $coupon->discount_value;
        }

        // Giảm giá không được vượt quá tổng giá gốc
        $discountAmount = min($discountAmount, $totalPrice);

        return [
            'coupon' => $coupon,
            'discount_amount' => (int) $discountAmount
        ];
    }
}
