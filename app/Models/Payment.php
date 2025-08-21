<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'provider',
        'transaction_id',
        'payment_time',
    ];

    /**
     * Mỗi payment thuộc về 1 booking.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
