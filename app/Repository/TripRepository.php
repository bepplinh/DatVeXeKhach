<?php

namespace App\Repository;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repository\Interfaces\TripRepositoryInterface;

class TripRepository implements TripRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Trip::with([
            'route.fromCity:id,name,type',
            'route.toCity:id,name,type',
            'bus'
        ]);

        if (!empty($filters['route_id'])) {
            $query->where('route_id', $filters['route_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['bus_id'])) {
            $query->where('bus_id', $filters['bus_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('departure_time', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('departure_time', '<=', $filters['date_to']);
        }
        if (!empty($filters['from_city']) && !empty($filters['to_city'])) {
            $from = (int) $filters['from_city'];
            $to = (int) $filters['to_city'];
            $direction = $filters['direction'] ?? 'both'; // forward|reverse|both

            $query->whereHas('route', function ($q) use ($from, $to, $direction) {
                if ($direction === 'forward') {
                    $q->where('from_city', $from)->where('to_city', $to);
                } elseif ($direction === 'reverse') {
                    $q->where('from_city', $to)->where('to_city', $from);
                } else {
                    $q->where(function ($q2) use ($from, $to) {
                        $q2->where(function ($q3) use ($from, $to) {
                            $q3->where('from_city', $from)->where('to_city', $to);
                        })->orWhere(function ($q4) use ($from, $to) {
                            $q4->where('from_city', $to)->where('to_city', $from);
                        });
                    });
                }
            });
        }

        $now = now();
        $query->orderByRaw("CASE WHEN departure_time >= ? THEN 0 ELSE 1 END ASC", [$now])
              ->orderBy('departure_time', 'asc')
              ->orderBy('departure_time', 'desc');

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?Trip
    {
        return Trip::with(['route.fromCity', 'route.toCity', 'bus'])->find($id);
    }

    public function create(array $data): Trip
    {
        return Trip::create($data);
    }

    public function update(int $id, array $data): ?Trip
    {
        $trip = Trip::find($id);
        if (!$trip) {
            return null;
        }
        $trip->update($data);
        return $trip->load(['route.fromCity', 'route.toCity', 'bus']);
    }

    public function delete(int $id): bool
    {
        $trip = Trip::find($id);
        return $trip ? (bool) $trip->delete() : false;
    }
} 