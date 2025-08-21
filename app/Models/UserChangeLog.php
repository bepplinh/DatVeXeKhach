<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'field_name',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'changed_at',
        'is_suspicious',
        'suspicious_reason',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'is_suspicious' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kiểm tra xem thay đổi có đáng ngờ không
     */
    public function isSuspiciousChange(): bool
    {
        // Kiểm tra thay đổi birthday quá thường xuyên
        if ($this->field_name === 'birthday') {
            $recentChanges = static::where('user_id', $this->user_id)
                ->where('field_name', 'birthday')
                ->where('changed_at', '>=', now()->subDays(30))
                ->count();

            if ($recentChanges > 2) { // Quá 2 lần thay đổi trong 30 ngày
                return true;
            }
        }

        // Kiểm tra thay đổi email quá thường xuyên
        if ($this->field_name === 'email') {
            $recentChanges = static::where('user_id', $this->user_id)
                ->where('field_name', 'email')
                ->where('changed_at', '>=', now()->subDays(30))
                ->count();

            if ($recentChanges > 3) { // Quá 3 lần thay đổi trong 30 ngày
                return true;
            }
        }

        return false;
    }

    /**
     * Đánh dấu thay đổi là đáng ngờ
     */
    public function markAsSuspicious(string $reason): void
    {
        $this->update([
            'is_suspicious' => true,
            'suspicious_reason' => $reason,
        ]);
    }
}
