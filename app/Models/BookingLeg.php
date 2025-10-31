<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingLeg extends Model
{
    use HasFactory;

    protected $table = 'booking_legs';

    protected $fillable = [
        'booking_id',
        'leg_type',
        'trip_id',
        'pickup_location_id',
        'dropoff_location_id',
        'pickup_address',
        'dropoff_address',
        'total_price',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class, 'booking_leg_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
