<?php

namespace App\Console\Commands;

use App\Models\CouponUser;
use App\Models\User;
use App\Models\UserChangeLog;
use App\Services\SecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SecurityScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:scan {--action=all : Action to perform (all, suspicious-users, coupon-abuse, rate-limits)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quét và xử lý các hành vi đáng ngờ trong hệ thống';

    protected SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        parent::__construct();
        $this->securityService = $securityService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->option('action');

        $this->info('🔒 Bắt đầu quét bảo mật...');

        try {
            switch ($action) {
                case 'suspicious-users':
                    $this->scanSuspiciousUsers();
                    break;
                case 'coupon-abuse':
                    $this->scanCouponAbuse();
                    break;
                case 'rate-limits':
                    $this->scanRateLimits();
                    break;
                case 'all':
                default:
                    $this->scanSuspiciousUsers();
                    $this->scanCouponAbuse();
                    $this->scanRateLimits();
                    break;
            }

            $this->info('✅ Hoàn thành quét bảo mật!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Có lỗi xảy ra: ' . $e->getMessage());
            Log::error('SecurityScanCommand error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Quét các user có hành vi đáng ngờ
     */
    private function scanSuspiciousUsers(): void
    {
        $this->info('🔍 Quét các user có hành vi đáng ngờ...');

        // Tìm các thay đổi đáng ngờ
        $suspiciousChanges = UserChangeLog::where('is_suspicious', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy('user_id');

        foreach ($suspiciousChanges as $userId => $changes) {
            $user = User::find($userId);
            if (!$user) continue;

            $this->warn("User {$user->email} có {$changes->count()} thay đổi đáng ngờ:");

            foreach ($changes as $change) {
                $this->line("  - {$change->field_name}: {$change->old_value} → {$change->new_value}");
                $this->line("    Lý do: {$change->suspicious_reason}");
            }

            // Tự động block user nếu có quá nhiều hành vi đáng ngờ
            if ($changes->count() >= 5) {
                $this->securityService->blockUser($userId, 'Quá nhiều hành vi đáng ngờ');
                $this->error("  → User {$user->email} đã bị block tự động!");
            }
        }

        $this->info("Tìm thấy " . $suspiciousChanges->count() . " user có hành vi đáng ngờ");
    }

    /**
     * Quét lạm dụng coupon
     */
    private function scanCouponAbuse(): void
    {
        $this->info('🎫 Quét lạm dụng coupon...');

        // Tìm các user nhận quá nhiều coupon sinh nhật
        $abusiveUsers = CouponUser::whereHas('coupon', function ($query) {
                $query->where('type', 'birthday');
            })
            ->where('received_at', '>=', now()->subDays(365))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($abusiveUsers as $abuse) {
            $user = User::find($abuse->user_id);
            if (!$user) continue;

            $couponCount = CouponUser::where('user_id', $abuse->user_id)
                ->whereHas('coupon', function ($query) {
                    $query->where('type', 'birthday');
                })
                ->where('received_at', '>=', now()->subDays(365))
                ->count();

            $this->warn("User {$user->email} đã nhận {$couponCount} coupon sinh nhật trong 1 năm:");

            // Đánh dấu tất cả coupon của user này là đáng ngờ
            CouponUser::where('user_id', $abuse->user_id)
                ->whereHas('coupon', function ($query) {
                    $query->where('type', 'birthday');
                })
                ->update([
                    'is_suspicious' => true,
                    'suspicious_reason' => "Nhận quá nhiều coupon sinh nhật ({$couponCount} lần trong 1 năm)"
                ]);

            $this->line("  → Đã đánh dấu {$couponCount} coupon là đáng ngờ");

            // Block user nếu lạm dụng nghiêm trọng
            if ($couponCount >= 3) {
                $this->securityService->blockUser($abuse->user_id, 'Lạm dụng coupon sinh nhật');
                $this->error("  → User {$user->email} đã bị block do lạm dụng coupon!");
            }
        }

        $this->info("Tìm thấy " . $abusiveUsers->count() . " user lạm dụng coupon");
    }

    /**
     * Quét rate limits
     */
    private function scanRateLimits(): void
    {
        $this->info('⏱️ Quét rate limits...');

        // Tìm các IP/user bị block
        $blockedEntries = \App\Models\RateLimit::where('is_blocked', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($blockedEntries as $entry) {
            $this->warn("Entry bị block: {$entry->key} - {$entry->action}");
            $this->line("  Lý do: {$entry->block_reason}");
            $this->line("  Thời gian: {$entry->created_at->diffForHumans()}");
        }

        $this->info("Tìm thấy " . $blockedEntries->count() . " entry bị block");
    }
}
