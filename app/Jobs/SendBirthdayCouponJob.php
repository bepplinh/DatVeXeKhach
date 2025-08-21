<?php

namespace App\Jobs;

use App\Mail\BirthdayCouponMail;
use App\Models\Coupon;
use App\Models\User;
use App\Services\CouponUserService;
use App\Services\SecurityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBirthdayCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected SecurityService $securityService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->securityService = app(SecurityService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(CouponUserService $couponUserService): void
    {
        try {
            Log::info('Bắt đầu kiểm tra và gửi coupon sinh nhật');

            // Lấy danh sách user có sinh nhật hôm nay
            $birthdayUsers = $this->getBirthdayUsers();
            
            if ($birthdayUsers->isEmpty()) {
                Log::info('Không có user nào có sinh nhật hôm nay');
                return;
            }

            // Lấy coupon sinh nhật mặc định
            $birthdayCoupon = $this->getBirthdayCoupon();
            
            if (!$birthdayCoupon) {
                Log::warning('Không tìm thấy coupon sinh nhật để gửi');
                return;
            }

            $successCount = 0;
            $errorCount = 0;
            $blockedCount = 0;

            foreach ($birthdayUsers as $user) {
                try {
                    // Kiểm tra bảo mật trước khi gửi coupon
                    if (!$this->securityService->canReceiveBirthdayCoupon($user)) {
                        Log::warning("User {$user->id} bị chặn nhận coupon sinh nhật do hành vi đáng ngờ");
                        $blockedCount++;
                        continue;
                    }

                    // Kiểm tra xem user đã có coupon sinh nhật này chưa
                    $existingCouponUser = $couponUserService->getCouponUsersByUserId($user->id)
                        ->where('coupon_id', $birthdayCoupon->id)
                        ->first();

                    if ($existingCouponUser) {
                        Log::info("User {$user->id} đã có coupon sinh nhật, bỏ qua");
                        continue;
                    }

                    // Tạo hash bảo mật cho coupon
                    $birthdayHash = $this->securityService->createSecureCouponHash($user, 'birthday');

                    // Gán coupon cho user với thông tin bảo mật
                    $couponUser = $couponUserService->assignCouponToUser($birthdayCoupon->id, $user->id, [
                        'birthday_hash' => $birthdayHash,
                        'received_at' => now(),
                        'ip_address' => request()->ip() ?? 'system',
                        'user_agent' => request()->userAgent() ?? 'system',
                    ]);
                    
                    // Gửi email
                    if ($user->email) {
                        Mail::to($user->email)->send(new BirthdayCouponMail($user, $birthdayCoupon));
                        Log::info("Đã gửi email coupon sinh nhật cho user {$user->id} ({$user->email})");
                        $successCount++;
                    } else {
                        Log::warning("User {$user->id} không có email, không thể gửi mail");
                        $errorCount++;
                    }

                } catch (\Exception $e) {
                    Log::error("Lỗi khi xử lý user {$user->id}: " . $e->getMessage());
                    $errorCount++;
                }
            }

            Log::info("Hoàn thành gửi coupon sinh nhật. Thành công: {$successCount}, Lỗi: {$errorCount}, Bị chặn: {$blockedCount}");

        } catch (\Exception $e) {
            Log::error("Lỗi trong SendBirthdayCouponJob: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy danh sách user có sinh nhật hôm nay
     */
    private function getBirthdayUsers()
    {
        $today = now();
        
        return User::whereNotNull('birthday')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereRaw("DATE_FORMAT(birthday, '%m-%d') = ?", [$today->format('m-d')])
            ->get();
    }

    /**
     * Lấy coupon sinh nhật mặc định
     */
    private function getBirthdayCoupon()
    {
        return Coupon::where('type', 'birthday')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->first();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendBirthdayCouponJob failed: ' . $exception->getMessage());
    }
}
