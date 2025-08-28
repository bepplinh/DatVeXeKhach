<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'code','trip_id','seat_id','user_id','coupon_id',
        'total_price','discount_amount','status','paid_at','cancelled_at'
      ];

    protected $casts = [
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
      ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
