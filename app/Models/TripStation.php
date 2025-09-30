<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripStation extends Model
{
	use HasFactory;

	protected $fillable = [
		'route_id',
		'from_location_id',
		'to_location_id',
		'price',
		'duration_minutes',
	];

	protected $casts = [
		'price' => 'integer',
		'duration_minutes' => 'integer',
	];

	public function route()
	{
		return $this->belongsTo(Route::class, 'route_id');
	}

	public function fromLocation()
	{
		return $this->belongsTo(Location::class, 'from_location_id');
	}

	public function toLocation()
	{
		return $this->belongsTo(Location::class, 'to_location_id');
	}
}
