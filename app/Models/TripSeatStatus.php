<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSeatStatus extends Model
{
    protected $fillable = ['trip_id', 'seat_id', 'is_booked', 'booked_by_user_id'];
    protected $casts = ['is_booked' => 'boolean'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }
}
