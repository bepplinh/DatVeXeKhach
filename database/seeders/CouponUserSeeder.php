<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CouponUser;
use App\Models\Coupon;
use App\Models\User;

class CouponUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy một số user và coupon để tạo dữ liệu mẫu
        $users = User::take(5)->get();
        $coupons = Coupon::take(3)->get();

        if ($users->isEmpty() || $coupons->isEmpty()) {
            $this->command->info('Không có đủ user hoặc coupon để tạo dữ liệu mẫu.');
            return;
        }

        foreach ($users as $user) {
            foreach ($coupons as $coupon) {
                // Tạo một số coupon-user với trạng thái khác nhau
                CouponUser::create([
                    'user_id' => $user->id,
                    'coupon_id' => $coupon->id,
                    'is_used' => rand(0, 1), // Random trạng thái sử dụng
                    'used_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null, // Random thời gian sử dụng
                ]);
            }
        }

        $this->command->info('Đã tạo dữ liệu mẫu cho bảng coupon_user.');
    }
}
