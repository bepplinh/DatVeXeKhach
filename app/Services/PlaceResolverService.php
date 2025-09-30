<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\TripStation;

class PlaceResolverService
{
    public function toLocationId(?string $text): ?int
    {
        $name = trim((string) $text);
        if ($name === '') {
            return null;
        }

        $exact = DB::table('locations')->where('name', $name)->value('id');
        if ($exact) {
            return (int) $exact;
        }

        $like = DB::table('locations')
            ->where('name', 'like', "%{$name}%")
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$name])
            ->value('id');

        return $like ? (int) $like : null;
    }

    /**
     * Tìm route_id từ text địa điểm đi và đến
     * Sử dụng logic giống TripSearchService
     */
    public function findRouteId(?string $fromText, ?string $toText): ?int
    {
        $fromId = $this->toLocationId($fromText);
        $toId = $this->toLocationId($toText);

        if (!$fromId || !$toId) {
            return null;
        }

        // Tìm route_id từ TripStation giống TripSearchService
        $routeId = TripStation::query()
            ->where('from_location_id', $fromId)
            ->where('to_location_id', $toId)
            ->value('route_id');

        return $routeId ? (int) $routeId : null;
    }

    /**
     * Tìm tất cả route_id có thể từ text địa điểm đi và đến
     * Hữu ích khi có nhiều route giữa 2 địa điểm
     */
    public function findAllRouteIds(?string $fromText, ?string $toText): array
    {
        $fromId = $this->toLocationId($fromText);
        $toId = $this->toLocationId($toText);

        if (!$fromId || !$toId) {
            return [];
        }

        return TripStation::query()
            ->where('from_location_id', $fromId)
            ->where('to_location_id', $toId)
            ->pluck('route_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    /**
     * Kiểm tra xem có route nào giữa 2 địa điểm không
     */
    public function hasRoute(?string $fromText, ?string $toText): bool
    {
        return $this->findRouteId($fromText, $toText) !== null;
    }
}


