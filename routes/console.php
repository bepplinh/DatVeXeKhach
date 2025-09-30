<?php

use App\Jobs\SendBirthdayCouponJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


// Command để gửi coupon sinh nhật
Artisan::command('coupons:send-birthday', function () {
    $this->info('🚀 Bắt đầu gửi coupon sinh nhật...');
    
    try {
        // Dispatch job để gửi coupon sinh nhật
        SendBirthdayCouponJob::dispatch();
        
        $this->info('✅ Job gửi coupon sinh nhật đã được đưa vào queue thành công!');
        $this->info('📧 Hãy kiểm tra logs để xem kết quả gửi email.');
        
    } catch (\Exception $e) {
        $this->error('❌ Có lỗi xảy ra: ' . $e->getMessage());
        return 1;
    }
    
    return 0;
})->purpose('Gửi coupon sinh nhật cho các user có sinh nhật hôm nay');

// Command để test gửi email (chỉ dùng cho development)
Artisan::command('coupons:test-birthday-email {email}', function ($email) {
    $this->info("🧪 Test gửi email coupon sinh nhật đến: {$email}");
    
    try {
        // Tạo user test
        $user = new \App\Models\User([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => $email,
            'birthday' => now()->format('Y-m-d')
        ]);
        
        // Tạo coupon test
        $coupon = new \App\Models\Coupon([
            'code' => 'BIRTHDAY2024',
            'name' => 'Coupon Sinh Nhật Test',
            'description' => 'Mã giảm giá sinh nhật đặc biệt',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'minimum_order_amount' => 100000,
            'max_discount_amount' => 50000,
            'type' => 'birthday',
            'is_active' => true
        ]);
        
        // Gửi email test
        Mail::to($email)->send(new \App\Mail\BirthdayCouponMail($user, $coupon));
        
        $this->info('✅ Email test đã được gửi thành công!');
        $this->info('📧 Hãy kiểm tra hộp thư của bạn.');
        
    } catch (\Exception $e) {
        $this->error('❌ Có lỗi xảy ra: ' . $e->getMessage());
        return 1;
    }
    
    return 0;
})->purpose('Test gửi email coupon sinh nhật (chỉ dùng cho development)');

// Command để kiểm tra user có sinh nhật hôm nay
Artisan::command('coupons:check-birthdays', function () {
    $this->info('🔍 Kiểm tra user có sinh nhật hôm nay...');
    
    try {
        $today = now();
        $birthdayUsers = \App\Models\User::whereNotNull('birthday')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw("DATE_FORMAT(birthday, '%m-%d') = ?", [$today->format('m-d')])
            ->get();
        
        if ($birthdayUsers->isEmpty()) {
            $this->info('📅 Không có user nào có sinh nhật hôm nay (' . $today->format('d/m/Y') . ')');
        } else {
            $this->info('🎂 Tìm thấy ' . $birthdayUsers->count() . ' user có sinh nhật hôm nay:');
            foreach ($birthdayUsers as $user) {
                $this->line("  - {$user->name} ({$user->email}) - Sinh nhật: " . $user->birthday->format('d/m/Y'));
            }
        }
        
        // Kiểm tra coupon sinh nhật
        $birthdayCoupon = \App\Models\Coupon::where('type', 'birthday')
            ->where('is_active', true)
            ->first();
        
        if ($birthdayCoupon) {
            $this->info('🎁 Tìm thấy coupon sinh nhật: ' . $birthdayCoupon->code);
        } else {
            $this->warn('⚠️  Không tìm thấy coupon sinh nhật nào!');
        }
        
    } catch (\Exception $e) {
        $this->error('❌ Có lỗi xảy ra: ' . $e->getMessage());
        return 1;
    }
    
    return 0;
})->purpose('Kiểm tra user có sinh nhật hôm nay và coupon sinh nhật');

Schedule::command('test:cron')->everyMinute();
