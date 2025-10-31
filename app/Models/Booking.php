<?php

namespace App\Models;

use App\Models\Trip;
use App\Models\User;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;
    protected $table = 'bookings';

    protected $fillable = [
        'code',
        'trip_id',
        'user_id',
        'coupon_id',
        'subtotal_price',
        'total_price',
        'discount_amount',
        'status',
        'payment_provider',
        'payment_intent_id',
        'passenger_name',
        'passenger_phone',
        'passenger_email',
        'origin_location_id',
        'destination_location_id',
        'pickup_address',
        'dropoff_address',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal_price' => 'decimal:0',
        'total_price' => 'decimal:0',
        'discount_amount' => 'decimal:0',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function legs()
    {
        return $this->hasMany(BookingLeg::class);
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
