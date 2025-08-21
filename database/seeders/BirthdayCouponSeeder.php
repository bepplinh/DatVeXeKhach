<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Coupon;

class BirthdayCouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo coupon sinh nhật mặc định
        Coupon::create([
            'code' => 'BIRTHDAY2024',
            'name' => '🎂 Coupon Sinh Nhật Đặc Biệt',
            'description' => 'Chúc mừng sinh nhật! Giảm giá đặc biệt cho ngày đặc biệt của bạn.',
            'discount_type' => 'percentage',
            'discount_value' => 15, // Giảm 15%
            'minimum_order_amount' => 100000, // Áp dụng cho đơn hàng từ 100k
            'type' => 'birthday',
            'max_usage' => null, // Không giới hạn số lần sử dụng
            'used_count' => 0,
            'valid_from' => now()->startOfYear(), // Có hiệu lực từ đầu năm
            'valid_until' => now()->endOfYear(), // Có hiệu lực đến cuối năm
            'is_active' => true,
        ]);

        // Tạo thêm một số coupon sinh nhật khác
        Coupon::create([
            'code' => 'BIRTHDAY20K',
            'name' => '🎁 Coupon Sinh Nhật 20K',
            'description' => 'Giảm giá cố định 20,000 VNĐ cho sinh nhật của bạn.',
            'discount_type' => 'fixed',
            'discount_value' => 20000, // Giảm 20k
            'minimum_order_amount' => 50000, // Áp dụng cho đơn hàng từ 50k
            'type' => 'birthday',
            'max_usage' => null,
            'used_count' => 0,
            'valid_from' => now()->startOfYear(),
            'valid_until' => now()->endOfYear(),
            'is_active' => true,
        ]);

        $this->command->info('✅ Đã tạo coupon sinh nhật mẫu thành công!');
        $this->command->info('🎂 BIRTHDAY2024: Giảm 15% cho đơn hàng từ 100k');
        $this->command->info('🎁 BIRTHDAY20K: Giảm 20k cố định cho đơn hàng từ 50k');
    }
}
