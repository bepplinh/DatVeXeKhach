<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatLayoutTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'decks',
        'total_seats'
    ];

    public function templateSeats()
    {
        return $this->hasMany(TemplateSeat::class, 'seat_layout_template_id');
    }
}
