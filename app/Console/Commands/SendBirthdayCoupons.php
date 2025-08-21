<?php

namespace App\Console\Commands;

use App\Jobs\SendBirthdayCouponJob;
use Illuminate\Console\Command;

class SendBirthdayCoupons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:send-birthday {--force : Force send even if already sent today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gửi coupon sinh nhật cho các user có sinh nhật hôm nay';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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
    }
}
