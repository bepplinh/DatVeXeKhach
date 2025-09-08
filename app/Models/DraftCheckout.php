<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class DraftCheckout extends Model
{
    protected $fillable = [
        'trip_id',
        'seat_ids',
        'user_id',
        'passenger_name',
        'passenger_phone',
        'passenger_email',
        'pickup_location_id',
        'dropoff_location_id',
        'pickup_address',
        'dropoff_address',
        'total_price',
        'discount_amount',
        'coupon_id',
        'notes',
        'passenger_info',
        'status',
        'expires_at',
        'completed_at',
        'session_id',
        'checkout_token',
    ];

    protected $casts = [
        'seat_ids' => 'array',
        'passenger_info' => 'array',
        'total_price' => 'decimal:0',
        'discount_amount' => 'decimal:0',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dropoffLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function seats()
    {
        return Seat::whereIn('id', $this->seat_ids ?? []);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'draft')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->orWhere('status', 'expired');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    // Methods
    public function isExpired(): bool
    {
        return $this->expires_at <= now() || $this->status === 'expired';
    }

    public function isActive(): bool
    {
        return $this->status === 'draft' && !$this->isExpired();
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    public function extendExpiration(int $minutes = 15): void
    {
        $this->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    // Boot method để tự động tạo checkout_token
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($draftCheckout) {
            if (empty($draftCheckout->checkout_token)) {
                $draftCheckout->checkout_token = 'draft_' . uniqid() . '_' . time();
            }
            if (empty($draftCheckout->expires_at)) {
                $draftCheckout->expires_at = now()->addMinutes(30); // Mặc định 30 phút
            }
        });
    }
}
