<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_city',
        'to_city',
        'name',
    ];

    /**
     * Thành phố điểm đi (ví dụ: Thanh Hóa).
     */ 
    public function fromCity()
    {
        return $this->belongsTo(Location::class, 'from_city');
    }

    /**
     * Thành phố điểm đến (ví dụ: Hà Nội).
     */
    public function toCity()
    {
        return $this->belongsTo(Location::class, 'to_city');
    }

    /**
     * Danh sách các chuyến xe thuộc tuyến này.
     */
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function tripStations()
    {
        return $this->hasMany(TripStation::class, 'route_id');
    }
}
