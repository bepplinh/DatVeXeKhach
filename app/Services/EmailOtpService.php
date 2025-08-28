<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EmailOtpService
{
    public function __construct(private int $ttlSeconds = 600) {} // 10 phút

    private function key(string $email): string
    {
        return 'email_otp:' . strtolower(trim($email));
    }

    public function start(string $email): void
    {
        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($email), $code, $this->ttlSeconds);

        // Gửi mail đơn giản (có thể đổi sang Mailable)
        Mail::raw("Mã OTP của bạn: {$code}. Hiệu lực 10 phút.", function ($m) use ($email) {
            $m->to(strtolower(trim($email)))->subject('Mã xác thực đăng ký/đăng nhập');
        });
    }

    public function check(string $email, string $code): bool
    {
        $cached = Cache::get($this->key($email));
        if ($cached && hash_equals($cached, $code)) {
            Cache::forget($this->key($email));
            return true;
        }
        return false;
    }
}
