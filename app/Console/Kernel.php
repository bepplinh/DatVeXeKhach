<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Dọn dẹp draft checkouts hết hạn mỗi 15 phút
        $schedule->command('draft:cleanup')->everyFifteenMinutes();
        
        // Gửi birthday coupons hàng ngày lúc 9:00
        $schedule->command('coupons:send-birthday')->dailyAt('09:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
