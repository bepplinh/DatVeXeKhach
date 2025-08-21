<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusType extends Model
{
    use HasFactory;

    protected $table = 'type_buses';

    protected $fillable = [
        'name',
        'seat_count',
    ];

    /**
     * 1 loại xe có nhiều xe cụ thể
     */
    public function buses()
    {
        return $this->hasMany(Bus::class, 'type_bus_id');
    }
}
