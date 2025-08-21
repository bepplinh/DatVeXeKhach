<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatEvent extends Model
{
    protected $fillable = ['trip_id','seat_id','user_id','type','meta'];
    protected $casts = ['meta' => 'array'];

    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
    public function seat(): BelongsTo { return $this->belongsTo(Seat::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
