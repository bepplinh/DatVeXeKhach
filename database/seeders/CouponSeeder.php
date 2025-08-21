<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code'               => 'DISCOUNT100',
                'name'               => 'Giảm 100K',
                'description'        => 'Giảm cố định 100,000đ cho đơn hàng tối thiểu 500,000đ',
                'discount_type'      => 'fixed',
                'discount_value'     => 100000,
                'minimum_order_amount' => 500000,
                'max_usage'          => 100,
                'used_count'         => 0,
                'valid_from'         => Carbon::now(),
                'valid_until'        => Carbon::now()->addMonths(1),
                'is_active'          => true,
            ],
            [
                'code'               => 'PERCENT10',
                'name'               => 'Giảm 10%',
                'description'        => 'Giảm 10% cho đơn hàng tối thiểu 300,000đ (tối đa 50,000đ)',
                'discount_type'      => 'percentage',
                'discount_value'     => 10,
                'minimum_order_amount' => 300000,
                'max_usage'          => 200,
                'used_count'         => 0,
                'valid_from'         => Carbon::now(),
                'valid_until'        => Carbon::now()->addMonths(2),
                'is_active'          => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}
