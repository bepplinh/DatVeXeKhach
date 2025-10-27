<?php

namespace App\Services\SeatFlow;

use App\Events\SeatLocked;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use App\Services\DraftCheckoutService\DraftCheckoutService;

class SeatLockService
{
    const DEFAULT_TTL = 180;

    /**
     * API chính: Checkout (lock ghế + tạo draft)
     *
     * @param  array   $trips ex:
     * [
     *   ['trip_id' => 1, 'seat_ids' => [3,4,5]],
     *   ['trip_id' => 2, 'seat_ids' => [1,2]],
     * ]
     * @param  string  $token  session token (duy nhất cho client)
     * @param  int     $ttlSeconds  TTL khoá ghế (giây)
     * @param  ?int    $userId  (nullable nếu khách vãng lai)
     * @return array   payload cho FE
     */

    public function __construct(
        private DraftCheckoutService $drafts,
    ) {}

    public function lock(
        array $trips,
        string $sessionToken,
        int $ttl = self::DEFAULT_TTL,
        ?int $userId = null,
    ) {
        [$seatsByTrip, $legsByTrip] = $this->normalizeTripsWithLeg($trips);

        if (empty($seatsByTrip)) {
            throw ValidationException::withMessages([
                'trips' => ['Không có ghế hợp lệ để lock.'],
            ]);
        }

        $this->lockSeatsAcrossTrips($seatsByTrip, $sessionToken, $ttl);

        $draft = $this->drafts->createFromLocks(
            seatsByTrip: $seatsByTrip,
            legsByTrip: $legsByTrip,
            token: $sessionToken,
            userId: $userId,
            ttlSeconds: $ttl
        );
        // --- (D) TTL còn lại cho từng ghế để FE hiển thị countdown ---
        $ttlLeft = $this->ttlLeftForSeats($seatsByTrip);

        // gắn ttl_left vào items trả về
        $items = array_map(function ($it) use ($ttlLeft) {
            $k = $it['trip_id'] . ':' . $it['seat_id'];
            $it['ttl_left'] = $ttlLeft[$k] ?? null;
            return $it;
        }, $draft['items']);

        $locksPayload = [];
        foreach ($seatsByTrip as $tripId => $seatIds) {
            $locksPayload[] = [
                'trip_id' => (int) $tripId,
                'seat_ids' => array_values($seatIds),
                'leg'      => $legsByTrip[$tripId] ?? null, // OUT/RETURN nếu có
            ];
        }
        SeatLocked::dispatch($sessionToken, $locksPayload);

        return [
            'success'    => true,
            'draft_id'   => $draft['draft_id'],
            'status'     => $draft['status'],
            'expires_at' => $draft['expires_at'],
            'totals'     => $draft['totals'],
            'items'      => $items,
        ];
    }

    private function normalizeTripsWithLeg(array $trips): array
    {
        $seatsByTrip = [];
        $legsByTrip  = [];

        foreach ($trips as $t) {
            $tripId  = (int)($t['trip_id'] ?? 0);
            $seatIds = array_values(array_unique(array_map('intval', (array)($t['seat_ids'] ?? []))));
            $seatIds = array_values(array_filter($seatIds, fn($v) => $v > 0));

            if ($tripId <= 0 || empty($seatIds)) continue;

            $seatsByTrip[$tripId] = $seatIds;

            // parse leg
            $leg = strtoupper(trim((string)($t['leg'] ?? '')));
            $legsByTrip[$tripId] = in_array($leg, ['OUT', 'RETURN'], true) ? $leg : null;
        }

        return [$seatsByTrip, $legsByTrip];
    }


    private function normalizeSeatIds(array $seatIds)
    {
        $seatIds = array_map('intval', (array)$seatIds);
        $seatIds = array_values(array_unique($seatIds));
        return array_values(array_filter($seatIds, fn($v) => $v > 0));
    }

    private function lockSeatsAcrossTrips(array $seatsByTrip, string $token, int $ttl): void
    {
        $lua = <<<'LUA'
local payload = cjson.decode(ARGV[1])
local token   = ARGV[2]
local ttl     = tonumber(ARGV[3])

for trip_id_str, seat_list in pairs(payload) do
    local trip_id = tostring(trip_id_str)
    for _, seat_id in ipairs(seat_list) do
        local seatKey   = "trip:" .. trip_id .. ":seat:" .. seat_id .. ":lock"
        local bookedKey = "trip:" .. trip_id .. ":booked"

        -- nếu đã booked
        if redis.call("SISMEMBER", bookedKey, seat_id) == 1 then
            return {err = "Ghế " .. seat_id .. " đã được đặt (booked) trong trip " .. trip_id}
        end

        -- nếu đang lock bởi người khác
        local current = redis.call("GET", seatKey)
        if current and current ~= token then
            return {err = "Ghế " .. seat_id .. " đang được giữ bởi session khác trong trip " .. trip_id}
        end

        -- set lock với TTL
        redis.call("SET", seatKey, token, "EX", ttl)
        redis.call("SADD", "trip:" .. trip_id .. ":locked", seat_id)
        redis.call("SADD", "session:" .. token .. ":seats", trip_id .. ":" .. seat_id)
    end
end

return "OK"
LUA;

        $payload = json_encode($seatsByTrip, JSON_UNESCAPED_UNICODE);

        $res = Redis::eval($lua, 0, $payload, $token, $ttl);
        if ($res !== 'OK') {
            // Redis trả lỗi từ {err=...}
            $msg = is_string($res) ? $res : 'Lỗi khi lock ghế. Vui lòng thử lại.';
            throw ValidationException::withMessages([
                'seats' => [$msg],
            ]);
        }
    }

    private function ttlLeftForSeats(array $seatsByTrip): array
    {
        $map = [];
        foreach ($seatsByTrip as $tripId => $seatIds) {
            foreach ($seatIds as $sid) {
                $key = "trip:{$tripId}:seat:{$sid}:lock";
                $ttl = (int) Redis::ttl($key);
                $map["{$tripId}:{$sid}"] = $ttl > 0 ? $ttl : null;
            }
        }
        return $map;
    }
}
