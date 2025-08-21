<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DefaultCouponsSeeder extends Seeder
{
    public function run()
    {
        Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'welcome',
            'name' => 'Welcome Coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(6),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'LOYAL50',
            'type' => 'loyalty',
            'name' => 'Loyalty Coupon',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'minimum_order_amount' => 200000,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'BIRTHDAY20',
            'type' => 'birthday',
            'name' => 'Birthday Gift',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'minimum_order_amount' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
        ]);
    }
}
