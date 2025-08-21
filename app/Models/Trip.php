<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
	use HasFactory;

	protected $fillable = [
		'route_id',
		'bus_id',
		'departure_time',
		'status',
	];

	protected $casts = [
		'departure_time' => 'datetime',
	];

	/** Thuộc tuyến nào */
	public function route()
	{
		return $this->belongsTo(Route::class);
	}

	/** Xe chạy chuyến này */
	public function bus()
	{
		return $this->belongsTo(Bus::class);
	}

	/** Các trạm đón / trả của chuyến */
	public function tripStations()
	{
		return $this->hasMany(TripStation::class);
	}

	/** Trạm đón theo hướng đi */
	public function pickStations()
	{
		return $this->hasMany(TripStation::class)->where('type', 'pick')->orderBy('sort_order');
	}

	/** Trạm trả theo hướng đi */
	public function dropStations()
	{
		return $this->hasMany(TripStation::class)->where('type', 'drop')->orderBy('sort_order');
	}
}
