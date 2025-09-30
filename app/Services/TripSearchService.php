<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\SeatAvailabilityService;

class TripSearchService
{
    public function __construct(private SeatAvailabilityService $seatAvailability) {}

    public function searchOneWay(
        int $fromLocationId,
        int $toLocationId,
        string $dateYmd,
        array $filters
    )
    {
        $now = Carbon::now(); 

        $routeId = TripStation::query()
        ->where('from_location_id', $fromLocationId)
        ->where('to_location_id', $toLocationId)
        ->pluck('route_id');

        if ($routeId->isEmpty()) return [];

        $dateStart = $dateYmd.' 00:00:00';
        $dateEnd   = $dateYmd.' 23:59:59';

        $q = Trip::query()
            ->with(['route','route.tripStations' => function ($q) use ($fromLocationId, $toLocationId) {
                $q->where('from_location_id', $fromLocationId)
                  ->where('to_location_id', $toLocationId);
            },
            'bus.typeBus', 'bus.seatLayoutTemplate']) 
            ->whereIn('route_id', $routeId)
            ->whereBetween('departure_time', [$dateStart, $dateEnd]);

        // ====== FILTERS ======
        if(!empty($filters['bus_type'])) {
           $ids = (array)$filters['bus_type'];
           $q->whereHas('bus.typeBus', fn($sub) => $sub->whereIn('id', $ids));
        }

        if (!empty($filters['time_from']) && !empty($filters['time_to'])) {
            $q->whereBetween(DB::raw('TIME(departure_time)'), [
                $filters['time_from'], $filters['time_to']
            ]);
        }

        // Price cap filter if provided (uses trip_stations.price for the leg)
        if (!empty($filters['price_cap'])) {
            $priceCap = (int) $filters['price_cap'];
            $q->whereHas('route.tripStations', function ($sub) use ($priceCap, $fromLocationId, $toLocationId) {
                $sub->where('from_location_id', $fromLocationId)
                    ->where('to_location_id', $toLocationId)
                    ->where('price', '<=', $priceCap);
            });
        }


        $sort = $filters['sort'] ?? 'asc';
        $q->orderBy('departure_time', $sort);

        // limit (không bắt buộc)
        if (!empty($filters['limit'])) {
            $q->limit((int)$filters['limit']);
        }

        $rows = $q->get();

        // Tính counters cho tất cả trip trong 1 query
        $tripIds  = $rows->pluck('id')->all();
        $counters = $this->seatAvailability->countersForTrips($tripIds, $now);


        // chuẩn hoá output và filter theo số ghế còn trống
        $results = $rows->map(function ($trip) use ($filters, $counters) {
            $tripStation = $trip->route?->tripStations?->first();
            if (!$tripStation) return null;

            $duration = (int) data_get($tripStation, 'duration_minutes', 0);
            $hours = intdiv($duration, 60);
            $minutes = $duration % 60;

            // departure/arrival time
            $dep = $trip->departure_time ? Carbon::parse($trip->departure_time) : null;
            $arrival = $dep ? $dep->copy()->addMinutes($duration) : null;

            $total_seats = (int) optional(optional($trip->bus)->seatLayoutTemplate)->total_seats ?? 0;

            $ctr = $counters->get($trip->id);
            $booked  = (int) data_get($ctr, 'booked', 0);
            $locked  = (int) data_get($ctr, 'locked', 0);
            $available = max(0, $total_seats - $booked - $locked);

            // Filter theo min_seats nếu có
            $minSeats = $filters['min_seats'] ?? 0;
            if ($minSeats > 0 && $available < $minSeats) {
                return null; // Loại bỏ trip không đủ ghế
            }

            return [
                'id'              => $trip->id,
                'route_id'        => $trip->route_id,
                'route_name'      => $trip->route->name ?? null,
                'bus_id'          => $trip->bus_id,
                'day'             => $dep ? $dep->format('Y-m-d') : null,  
                'departure_time'  => $dep ? $dep->format('H:i') : null,
                'arrival_time'    => $arrival ? $arrival->format('H:i') : null,

                'duration'       => $duration,
                'duration_text'  => ($hours ? $hours.'h' : '') . ($minutes ? $minutes.'m' : '0m'),

                'price'           => optional(optional($trip->route)->tripStations->first())->price ?? null,
                'total_seats'     => $total_seats,
                'seats_booked'    => $booked,
                'seats_locked'    => $locked,
                'available_seats' => $available,
                'bus' => [
                    'name'         => optional($trip->bus)->name,
                    'code'         => optional($trip->bus)->code,
                    'plate_number' => optional($trip->bus)->plate_number,
                    'type'         => optional(optional($trip->bus)->typeBus)->name,
                   
                ],
                'route' => [
                    'from_city_id' => optional($trip->route)->from_city,
                    'to_city_id'   => optional($trip->route)->to_city,
                ],
            ];
        })->filter()->values()->all();
        return $results;
    }
}