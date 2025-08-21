<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RateLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'action',
        'attempts',
        'first_attempt_at',
        'last_attempt_at',
        'reset_at',
        'is_blocked',
        'block_reason',
    ];

    protected $casts = [
        'first_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'reset_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    /**
     * Kiểm tra và cập nhật rate limit
     */
    public static function checkAndUpdate(string $key, string $action, int $maxAttempts, int $decayMinutes): bool
    {
        $rateLimit = static::where('key', $key)
            ->where('action', $action)
            ->first();

        $now = now();

        if (!$rateLimit) {
            // Tạo mới rate limit
            static::create([
                'key' => $key,
                'action' => $action,
                'attempts' => 1,
                'first_attempt_at' => $now,
                'last_attempt_at' => $now,
                'reset_at' => $now->copy()->addMinutes($decayMinutes),
            ]);
            return true;
        }

        // Kiểm tra xem có bị block không
        if ($rateLimit->is_blocked) {
            return false;
        }

        // Kiểm tra xem có cần reset không
        if ($now->isAfter($rateLimit->reset_at)) {
            $rateLimit->update([
                'attempts' => 1,
                'first_attempt_at' => $now,
                'last_attempt_at' => $now,
                'reset_at' => $now->copy()->addMinutes($decayMinutes),
            ]);
            return true;
        }

        // Kiểm tra số lần thử
        if ($rateLimit->attempts >= $maxAttempts) {
            $rateLimit->update([
                'is_blocked' => true,
                'block_reason' => "Vượt quá giới hạn {$maxAttempts} lần trong {$decayMinutes} phút",
            ]);
            return false;
        }

        // Cập nhật số lần thử
        $rateLimit->update([
            'attempts' => $rateLimit->attempts + 1,
            'last_attempt_at' => $now,
        ]);

        return true;
    }

    /**
     * Reset rate limit
     */
    public static function reset(string $key, string $action): void
    {
        static::where('key', $key)
            ->where('action', $action)
            ->delete();
    }

    /**
     * Block một key
     */
    public static function block(string $key, string $action, string $reason): void
    {
        static::where('key', $key)
            ->where('action', $action)
            ->update([
                'is_blocked' => true,
                'block_reason' => $reason,
            ]);
    }
}
