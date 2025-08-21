<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Gửi coupon sinh nhật mỗi ngày lúc 9:00 sáng
        $schedule->command('coupons:send-birthday')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                Log::info('✅ Cron job gửi coupon sinh nhật đã chạy thành công');
            })
            ->onFailure(function () {
                Log::error('❌ Cron job gửi coupon sinh nhật thất bại');
            });

        // Cleanup failed jobs mỗi tuần
        $schedule->command('queue:prune-failed')
            ->weekly()
            ->onSuccess(function () {
                Log::info('🧹 Đã dọn dẹp failed jobs');
            });

        // Cleanup old logs mỗi tháng
        $schedule->command('log:clear')
            ->monthly()
            ->onSuccess(function () {
                Log::info('📝 Đã dọn dẹp logs cũ');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        // Đăng ký commands từ routes/console.php
        require base_path('routes/console.php');
    }
}
