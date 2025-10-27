<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DraftCheckoutLeg extends Model
{
    use HasFactory;

    protected $table = 'draft_checkout_legs';

    protected $fillable = [
        'draft_checkout_id',
        'trip_id',
        'legs',
        'total_price',
        'pickup_location_id',
        'dropoff_location_id',
        'pickup_snapshot',
        'dropoff_snapshot',
        'pickup_address',
        'dropoff_address',
    ];

    protected $casts = [
        'pickup_snapshot' => 'array',
        'dropoff_snapshot' => 'array',
    ];

    /* ================== Relationships ================== */

    /**
     * Draft checkout cha (một leg thuộc về một draft_checkout)
     */
    public function draftCheckout()
    {
        return $this->belongsTo(DraftCheckout::class, 'draft_checkout_id');
    }

    /**
     * Liên kết đến trip
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Liên kết các items (ghế) thuộc leg này
     */
    public function items()
    {
        return $this->hasMany(DraftCheckoutItem::class, 'draft_checkout_leg_id');
    }
}