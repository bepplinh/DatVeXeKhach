<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    protected $fillable =  ['code', 'name', 'plate_number', 'type_bus_id', 'seat_layout_template_id', 'uses_custom_seats'];

    protected $casts = [
        'uses_custom_seats' => 'bool',
    ];

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function typeBus()
    {
        return $this->belongsTo(BusType::class, 'type_bus_id');
    }

    public function seatLayoutTemplate()
    {
        return $this->belongsTo(SeatLayoutTemplate::class, 'seat_layout_template_id');
    }
}
