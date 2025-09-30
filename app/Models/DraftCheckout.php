<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftCheckout extends Model
{
    protected $fillable = [
        'trip_id',
        'user_id',
        'session_token',
        'payment_provider',
        'payment_intent_id',
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
        'passenger_someone_name',
        'passenger_someone_phone',
        'notes',
        'passenger_info',
        'status',
        'expires_at',
        'completed_at',
    ];

    public function items()
    {
        return $this->hasMany(DraftCheckoutItem::class, 'draft_checkout_id');
    }
}
