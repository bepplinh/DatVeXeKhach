<?php

namespace App\Services;

use App\Models\CouponUser;
use App\Models\RateLimit;
use App\Models\User;
use App\Models\UserChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityService
{
    /**
     * Kiểm tra rate limit cho một hành động
     */
    public function checkRateLimit(string $key, string $action, int $maxAttempts, int $decayMinutes): bool
    {
        return RateLimit::checkAndUpdate($key, $action, $maxAttempts, $decayMinutes);
    }

    /**
     * Kiểm tra xem user có bị block không
     */
    public function isUserBlocked(int $userId, string $action): bool
    {
        $rateLimit = RateLimit::where('key', "user_{$userId}")
            ->where('action', $action)
            ->first();

        return $rateLimit && $rateLimit->is_blocked;
    }

    /**
     * Kiểm tra hành vi đáng ngờ khi thay đổi thông tin
     */
    public function checkSuspiciousBehavior(User $user, string $fieldName, $oldValue, $newValue, Request $request): bool
    {
        $isSuspicious = false;
        $reason = '';

        // Kiểm tra thay đổi birthday
        if ($fieldName === 'birthday') {
            $isSuspicious = $this->checkBirthdayChange($user, $oldValue, $newValue);
            if ($isSuspicious) {
                $reason = 'Thay đổi ngày sinh nhật quá thường xuyên';
            }
        }

        // Kiểm tra thay đổi email
        if ($fieldName === 'email') {
            $isSuspicious = $this->checkEmailChange($user, $oldValue, $newValue);
            if ($isSuspicious) {
                $reason = 'Thay đổi email quá thường xuyên';
            }
        }

        // Ghi log thay đổi
        $this->logUserChange($user, $fieldName, $oldValue, $newValue, $request, $isSuspicious, $reason);

        return $isSuspicious;
    }

    /**
     * Kiểm tra thay đổi birthday có đáng ngờ không
     */
    private function checkBirthdayChange(User $user, $oldValue, $newValue): bool
    {
        // Kiểm tra số lần thay đổi birthday trong 30 ngày gần đây
        $recentChanges = UserChangeLog::where('user_id', $user->id)
            ->where('field_name', 'birthday')
            ->where('changed_at', '>=', now()->subDays(30))
            ->count();

        // Nếu thay đổi quá 2 lần trong 30 ngày thì đáng ngờ
        if ($recentChanges >= 2) {
            return true;
        }

        // Kiểm tra xem có thay đổi để trùng với ngày hiện tại không (để nhận coupon)
        $today = now()->format('m-d');
        $newBirthday = \Carbon\Carbon::parse($newValue)->format('m-d');
        
        if ($today === $newBirthday && $oldValue !== $newValue) {
            // Kiểm tra xem user đã nhận coupon sinh nhật chưa
            $hasBirthdayCoupon = CouponUser::where('user_id', $user->id)
                ->whereHas('coupon', function ($query) {
                    $query->where('type', 'birthday');
                })
                ->where('received_at', '>=', now()->subDays(7))
                ->exists();

            if ($hasBirthdayCoupon) {
                return true; // Đáng ngờ vì đã nhận coupon rồi
            }
        }

        return false;
    }

    /**
     * Kiểm tra thay đổi email có đáng ngờ không
     */
    private function checkEmailChange(User $user, $oldValue, $newValue): bool
    {
        // Kiểm tra số lần thay đổi email trong 30 ngày gần đây
        $recentChanges = UserChangeLog::where('user_id', $user->id)
            ->where('field_name', 'email')
            ->where('changed_at', '>=', now()->subDays(30))
            ->count();

        // Nếu thay đổi quá 3 lần trong 30 ngày thì đáng ngờ
        return $recentChanges >= 3;
    }

    /**
     * Ghi log thay đổi thông tin user
     */
    private function logUserChange(User $user, string $fieldName, $oldValue, $newValue, Request $request, bool $isSuspicious, string $reason = ''): void
    {
        UserChangeLog::create([
            'user_id' => $user->id,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'changed_at' => now(),
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $reason,
        ]);

        if ($isSuspicious) {
            Log::warning("Phát hiện hành vi đáng ngờ: User {$user->id} thay đổi {$fieldName}", [
                'user_id' => $user->id,
                'field_name' => $fieldName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Kiểm tra xem user có thể nhận coupon sinh nhật không
     */
    public function canReceiveBirthdayCoupon(User $user): bool
    {
        // Kiểm tra xem user có bị block không
        if ($this->isUserBlocked($user->id, 'birthday_coupon_request')) {
            return false;
        }

        // Kiểm tra xem user đã nhận coupon sinh nhật trong 365 ngày gần đây chưa
        $lastBirthdayCoupon = CouponUser::where('user_id', $user->id)
            ->whereHas('coupon', function ($query) {
                $query->where('type', 'birthday');
            })
            ->orderBy('received_at', 'desc')
            ->first();

        if ($lastBirthdayCoupon && $lastBirthdayCoupon->received_at->isAfter(now()->subDays(365))) {
            return false;
        }

        // Kiểm tra xem có thay đổi birthday gần đây không
        $recentBirthdayChanges = UserChangeLog::where('user_id', $user->id)
            ->where('field_name', 'birthday')
            ->where('changed_at', '>=', now()->subDays(30))
            ->where('is_suspicious', true)
            ->exists();

        if ($recentBirthdayChanges) {
            return false;
        }

        return true;
    }

    /**
     * Kiểm tra xem user có thể sử dụng coupon không
     */
    public function canUseCoupon(int $userId, int $couponId): bool
    {
        // Kiểm tra rate limit cho việc sử dụng coupon
        $rateLimitKey = "user_{$userId}";
        if (!$this->checkRateLimit($rateLimitKey, 'coupon_use', 3, 60)) {
            return false;
        }

        // Kiểm tra xem coupon có hợp lệ không
        $couponUser = CouponUser::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->first();

        if (!$couponUser || !$couponUser->isValid()) {
            return false;
        }

        return true;
    }

    /**
     * Kiểm tra xem user có thể áp dụng coupon không
     */
    public function canApplyCoupon(int $userId, string $couponCode): bool
    {
        // Kiểm tra rate limit cho việc áp dụng coupon
        $rateLimitKey = "user_{$userId}";
        if (!$this->checkRateLimit($rateLimitKey, 'coupon_apply', 5, 60)) {
            return false;
        }

        // Kiểm tra xem user có bị block không
        if ($this->isUserBlocked($userId, 'coupon_apply')) {
            return false;
        }

        return true;
    }

    /**
     * Kiểm tra xem user có thể validate coupon không
     */
    public function canValidateCoupon(int $userId): bool
    {
        // Kiểm tra rate limit cho việc validate coupon
        $rateLimitKey = "user_{$userId}";
        return $this->checkRateLimit($rateLimitKey, 'coupon_validate', 10, 60);
    }

    /**
     * Tạo hash bảo mật cho coupon
     */
    public function createSecureCouponHash(User $user, string $couponType): string
    {
        $data = [
            'user_id' => $user->id,
            'birthday' => $user->birthday,
            'email' => $user->email,
            'coupon_type' => $couponType,
            'timestamp' => now()->timestamp,
        ];

        return hash('sha256', json_encode($data) . config('app.key'));
    }

    /**
     * Block một user
     */
    public function blockUser(int $userId, string $reason): void
    {
        // Block user trong rate limit
        RateLimit::block("user_{$userId}", 'all_actions', $reason);
        
        // Có thể thêm logic block user trong database nếu cần
        Log::warning("User {$userId} đã bị block: {$reason}");
    }

    /**
     * Unblock một user
     */
    public function unblockUser(int $userId): void
    {
        // Unblock user trong rate limit
        RateLimit::where('key', "user_{$userId}")
            ->update(['is_blocked' => false, 'block_reason' => null]);
        
        Log::info("User {$userId} đã được unblock");
    }
}
