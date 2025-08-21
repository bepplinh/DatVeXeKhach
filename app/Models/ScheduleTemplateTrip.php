<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplateTrip extends Model
{
    protected $fillable = ['route_id', 'bus_id', 'weekday', 'departure_time', 'active'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }
}
