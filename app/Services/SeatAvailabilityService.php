<?php

namespace App\Services;

use App\Models\TripSeatStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

class SeatAvailabilityService
{
    /**
     * Đếm số ghế theo từng chuyến:
     *  - total: tổng số ghế trong DB
     *  - booked: ghế đã thanh toán thành công (ghi DB)
     *  - locked: ghế đang bị khóa tạm thời (Redis)
     */
    public function countersForTrips(array $tripIds, Carbon $now): Collection
    {
        if (empty($tripIds)) return collect();

        $tripIds = array_values(array_unique($tripIds));
        $nowTs   = $now->getTimestamp();

        // 1) Lấy total + booked từ DB
        $rows = TripSeatStatus::query()
            ->whereIn('trip_id', $tripIds)
            ->select('trip_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_booked = 1 THEN 1 ELSE 0 END) AS booked')
            ->groupBy('trip_id')
            ->get()
            ->keyBy('trip_id');

        // 2) Lấy locked từ Redis
        $lockedByTrip = $this->lockedCountForTrips($tripIds, $nowTs);

        // 3) Hợp nhất kết quả
        return collect($tripIds)->map(function ($id) use ($rows, $lockedByTrip) {
            $row = $rows->get($id);
            return [
                'trip_id'  => $id,
                'total'    => (int) ($row->total   ?? 0),
                'booked'   => (int) ($row->booked  ?? 0),
                'locked'   => (int) ($lockedByTrip[$id] ?? 0),
            ];
        })->keyBy('trip_id');
    }

    /**
     * Đếm số ghế đang lock trên Redis cho nhiều trip
     */
    private function lockedCountForTrips(array $tripIds, int $nowTs): array
    {
        $zKeys = array_map(fn($id) => $this->zKey($id), $tripIds);

        // Xóa các lock đã hết hạn
        Redis::pipeline(function ($pipe) use ($zKeys, $nowTs) {
            foreach ($zKeys as $z) {
                $pipe->zRemRangeByScore($z, '-inf', $nowTs);
            }
        });

        // Đếm số ghế đang lock
        $results = Redis::pipeline(function ($pipe) use ($zKeys) {
            foreach ($zKeys as $z) {
                $pipe->zCard($z);
            }
        });

        $counts = [];
        foreach ($tripIds as $i => $tripId) {
            $counts[$tripId] = (int) ($results[$i] ?? 0);
        }

        return $counts;
    }

    private function zKey(int $tripId): string
    {
        return "trip:{$tripId}:locks:z";
    }
}
