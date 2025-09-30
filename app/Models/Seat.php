<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    protected $fillable = ['bus_id','seat_number','deck','column_group','index_in_column','active'];

    public function bus(): BelongsTo { return $this->belongsTo(Bus::class); }
    public function tripStatuses(): HasMany { return $this->hasMany(TripSeatStatus::class); }
    public function bookingItems(): HasMany { return $this->hasMany(BookingItem::class); }
}
