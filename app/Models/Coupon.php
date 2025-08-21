<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'max_usage',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users()
{
    return $this->belongsToMany(User::class, 'coupon_user')
                ->withPivot('is_used', 'used_at')
                ->withTimestamps();
}


    // Kiểm tra xem coupon có còn hiệu lực không
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->valid_from && $now < $this->valid_from) {
            return false;
        }

        if ($this->valid_until && $now > $this->valid_until) {
            return false;
        }

        if ($this->max_usage && $this->used_count >= $this->max_usage) {
            return false;
        }

        return true;
    }

    // Tính toán giá trị giảm giá
    public function calculateDiscount($orderAmount): float
    {
        if ($orderAmount < $this->minimum_order_amount) {
            return 0;
        }

        if ($this->discount_type === 'fixed') {
            return min($this->discount_value, $orderAmount);
        }

        // percentage
        return $orderAmount * ($this->discount_value / 100);
    }
}
