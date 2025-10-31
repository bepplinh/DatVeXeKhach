<?php

namespace App\Services\SeatFlow;


class ReleaseLocksAfterBooked
{
    protected ?string $luaReleaseAfterBookedSha = null;

    public function releaseLocksAfterBooked(int $tripId, array $seatIds, string $token, int $bookingId): array
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        if (empty($seatIds)) {
            return ['released' => 0, 'seats' => []];
        }
    
        $lockedSetKey  = "trip:{$tripId}:locked";
        $bookedSetKey  = "trip:{$tripId}:booked";
        $sessionSetKey = "session:{$token}:seats";
        $lockKeys      = array_map(fn($sid) => "trip:{$tripId}:seat:{$sid}:lock", $seatIds);
    
        $conn = \Illuminate\Support\Facades\Redis::connection();
    
        // Nạp script theo cách tương thích cả PhpRedis & Predis
        if (!$this->luaReleaseAfterBookedSha) {
            // Laravel Facade hỗ trợ Redis::script('load', $lua) trên cả 2 client
            $this->luaReleaseAfterBookedSha = \Illuminate\Support\Facades\Redis::script('load', $this->luaReleaseAfterBooked());
        }
    
        $keys = array_merge([$lockedSetKey, $sessionSetKey, $bookedSetKey], $lockKeys);
        $argv = array_merge([$token, (string)$tripId], array_map('strval', $seatIds));
    
        try {
            // Với Laravel + PhpRedis: evalSha($sha, $numKeys, ...$keysAndArgs)
            // Với Predis: cú pháp tương tự (numKeys trước, sau đó keys + args)
            $res = $conn->evalSha($this->luaReleaseAfterBookedSha, count($keys), ...$keys, ...$argv);
        } catch (\Throwable $e) {
            // Fallback khi SCRIPT FLUSH / cache miss hoặc client không hỗ trợ evalSha
            $res = $conn->eval($this->luaReleaseAfterBooked(), count($keys), ...$keys, ...$argv);
        }
    
        $released        = (int)($res[0] ?? 0);
        $releasedSeatIds = array_map('intval', array_slice($res, 1));
    
        return ['released' => $released, 'seats' => $releasedSeatIds];
    }
    

    protected function luaReleaseAfterBooked(): string
    {
        // KEYS[1] = trip:{tripId}:locked
        // KEYS[2] = session:{token}:seats            (mỗi phần tử dạng "tripId:seatId")
        // KEYS[3] = trip:{tripId}:booked
        // KEYS[4..] = trip:{tripId}:seat:{seatId}:lock
        // ARGV[1] = token
        // ARGV[2] = tripId (string)
        // ARGV[3..] = seatIds (string), tương ứng KEYS[4..]
        return <<<'LUA'
local lockedSet   = KEYS[1]
local sessionSet  = KEYS[2]
local bookedSet   = KEYS[3]
local token       = ARGV[1]
local tripId      = ARGV[2]

local total = 0
local releasedSeatIds = {}

-- i chạy qua từng lockKey
for i = 4, #KEYS do
  local lockKey = KEYS[i]
  local seatId  = ARGV[i - 1]  -- i=4 -> ARGV[3], i=5 -> ARGV[4], ...

  local holder = redis.call('GET', lockKey)
  if (not holder) or (holder == token) then
    -- xóa lock key (nếu đã hết hạn thì DEL chỉ idempotent)
    redis.call('DEL', lockKey)

    -- xóa khỏi set 'locked'
    redis.call('SREM', lockedSet, seatId)

    -- xóa khỏi session:{token}:seats  (phần tử là "tripId:seatId")
    redis.call('SREM', sessionSet, tripId .. ':' .. seatId)

    -- đánh dấu booked
    redis.call('SADD', bookedSet, seatId)

    total = total + 1
    table.insert(releasedSeatIds, tonumber(seatId))
  end
end

local resp = { total }
for _, sid in ipairs(releasedSeatIds) do
  table.insert(resp, sid)
end
return resp
LUA;
    }
}
