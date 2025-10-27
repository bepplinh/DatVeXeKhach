<?php

namespace App\Services\SeatFlow;

use App\Events\SeatUnlocked;
use Illuminate\Support\Facades\Redis;

class SeatReleaseService
{
    /**
     * Public API: Huỷ toàn bộ khoá ghế theo sessionToken.
     * Trả về mảng các cặp ghế đã release để FE/Realtime cập nhật.
     */
    public function cancelAllBySession(string $sessionToken): array
    {
        $result = $this->releaseAllBySession($sessionToken);

        // Gom nhóm theo trip để bắn realtime SeatUnlocked (nếu bạn dùng)
        $releasedPairs = $result['released'] ?? [];
        if (!empty($releasedPairs)) {
            // releasedPairs: ["{tripId}:{seatId}", ...]
            $byTrip = [];
            foreach ($releasedPairs as $pair) {
                [$tripId, $seatId] = array_map('intval', explode(':', $pair, 2));
                $byTrip[$tripId][] = $seatId;
            }

            $payload = [];
            foreach ($byTrip as $tripId => $seatIds) {
                $payload[] = [
                    'trip_id'  => (int)$tripId,
                    'seat_ids' => array_values(array_unique($seatIds)),
                ];
            }

            // phát realtime để FE un-highlight ghế
            if (class_exists(SeatUnlocked::class)) {
                SeatUnlocked::dispatch($sessionToken, $payload);
            }
        }

        return [
            'success'     => true,
            'released'    => $result['released']   ?? [],
            'dangling'    => $result['dangling']   ?? [],
            'mismatched'  => $result['mismatched'] ?? [],
        ];
    }

    /**
     * Core: Huỷ **toàn bộ** lock theo session token (atomic).
     * - Xoá key lock đúng token
     * - SREM khỏi set trip:{id}:locked
     * - SREM & DEL session:{token}:seats
     * - Tự dọn rác nếu TTL đã hết (dangling)
     */
    private function releaseAllBySession(string $token): array
    {
        $lua = <<<'LUA'
local token = ARGV[1]
local sessionSet = "session:" .. token .. ":seats"

local pairs = redis.call("SMEMBERS", sessionSet)
local released = {}
local dangling = {}   -- còn trong session set, nhưng key lock đã hết TTL
local mismatched = {} -- key lock tồn tại nhưng value != token

for _, pair in ipairs(pairs) do
    -- pair dạng "tripId:seatId"
    local delim = string.find(pair, ":")
    if delim then
        local trip_id = string.sub(pair, 1, delim - 1)
        local seat_id = string.sub(pair, delim + 1)
        local seatKey = "trip:" .. trip_id .. ":seat:" .. seat_id .. ":lock"
        local lockedSet = "trip:" .. trip_id .. ":locked"

        local cur = redis.call("GET", seatKey)
        if not cur then
            -- TTL đã hết -> dọn set cho sạch
            redis.call("SREM", sessionSet, pair)
            redis.call("SREM", lockedSet, seat_id)
            table.insert(dangling, pair)
        elseif cur ~= token then
            -- khoá bởi token khác -> không đụng key lock, chỉ dọn session set
            redis.call("SREM", sessionSet, pair)
            table.insert(mismatched, pair)
        else
            -- đúng token -> xoá lock + dọn set
            redis.call("DEL", seatKey)
            redis.call("SREM", lockedSet, seat_id)
            redis.call("SREM", sessionSet, pair)
            table.insert(released, pair)
        end
    end
end

-- Nếu set session đã rỗng, DEL luôn
if redis.call("SCARD", sessionSet) == 0 then
    redis.call("DEL", sessionSet)
end

return cjson.encode({
    released   = released,
    dangling   = dangling,
    mismatched = mismatched
})
LUA;

        $json = Redis::eval($lua, 0, $token);
        return json_decode((string)$json, true) ?: [];
    }
}
