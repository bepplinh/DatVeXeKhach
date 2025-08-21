<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponUser extends Model
{
    use HasFactory;

    protected $table = 'coupon_user';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'is_used',
        'used_at',
        'birthday_hash',
        'received_at',
        'ip_address',
        'user_agent',
        'is_suspicious',
        'suspicious_reason',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'received_at' => 'datetime',
        'is_suspicious' => 'boolean',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kiểm tra xem coupon có hợp lệ không
     */
    public function isValid(): bool
    {
        if ($this->is_used) {
            return false;
        }

        if ($this->is_suspicious) {
            return false;
        }

        // Kiểm tra thời hạn coupon
        if ($this->coupon && $this->coupon->valid_until && now()->isAfter($this->coupon->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Đánh dấu coupon là đáng ngờ
     */
    public function markAsSuspicious(string $reason): void
    {
        $this->update([
            'is_suspicious' => true,
            'suspicious_reason' => $reason,
        ]);
    }

    /**
     * Tạo hash cho ngày sinh nhật
     */
    public static function createBirthdayHash(string $birthday): string
    {
        return hash('sha256', $birthday . config('app.key'));
    }
}
