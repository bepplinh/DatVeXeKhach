<?php

namespace App\Repository;

use App\Models\TripStation;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repository\Interfaces\TripStationRepositoryInterface;

class TripStationRepository implements TripStationRepositoryInterface
{
    public function list(array $filters = [], int $perPage = 15, ?string $sortBy = null, string $sortDir = 'asc'): LengthAwarePaginator
    {
        $query = TripStation::with(['route.fromCity:id,name', 'route.toCity:id,name', 'fromLocation:id,name', 'toLocation:id,name']);

        if (!empty($filters['route_id'])) {
            $query->where('route_id', (int) $filters['route_id']);
        }
        if (!empty($filters['from_location_id'])) {
            $query->where('from_location_id', (int) $filters['from_location_id']);
        }
        if (!empty($filters['to_location_id'])) {
            $query->where('to_location_id', (int) $filters['to_location_id']);
        }
        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (int) $filters['price_max']);
        }
        if (!empty($filters['duration_min'])) {
            $query->where('duration_minutes', '>=', (int) $filters['duration_min']);
        }
        if (!empty($filters['duration_max'])) {
            $query->where('duration_minutes', '<=', (int) $filters['duration_max']);
        }
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->whereHas('route', function($sub) use ($q) {
                $sub->where('name', 'like', "%$q%");
            })->orWhereHas('fromLocation', function($sub) use ($q) {
                $sub->where('name', 'like', "%$q%");
            })->orWhereHas('toLocation', function($sub) use ($q) {
                $sub->where('name', 'like', "%$q%");
            });
        }

        if ($sortBy) {
            $allowed = ['price','duration_minutes','created_at'];
            if (in_array($sortBy, $allowed, true)) {
                $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
            }
        } else {
            $query->orderBy('price', 'asc');
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?TripStation
    {
        return TripStation::with(['route.fromCity', 'route.toCity', 'fromLocation', 'toLocation'])->find($id);
    }

    public function create(array $data): TripStation
    {
        return TripStation::create($data);
    }

    public function update(int $id, array $data): ?TripStation
    {
        $station = TripStation::find($id);
        if (!$station) return null;
        $station->update($data);
        return $station->load(['route.fromCity', 'route.toCity', 'fromLocation', 'toLocation']);
    }

    public function delete(int $id): bool
    {
        $station = TripStation::find($id);
        return $station ? (bool) $station->delete() : false;
    }
} 