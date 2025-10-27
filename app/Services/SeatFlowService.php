<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Redis;
use App\Services\DraftCheckoutService\DraftCheckoutService;

class SeatFlowService
{
    public const DEFAULT_TTL               = 180; // giây
    public const MAX_PER_SESSION_PER_TRIP  = 6;   // giới hạn ghế/phiên/trip

    private ?string $luaTryLockSha = null;
    private ?string $luaReleaseSha = null;

    public function __construct(
        private DraftCheckoutService $drafts,
    ) {}

    /* ============================================================
     |  PUBLIC API
     * ============================================================
     */

    /**
     * Multi-trip checkout (atomic): lock tất cả ghế của nhiều trip trong 1 lần.
     *
     * @param array       $trips   [['trip_id'=>101,'seat_ids'=>[12,13],'leg'=>'OUT'], ...]
     * @param string      $token   Session token duy nhất (ổn định cho phiên)
     * @param int         $ttlSeconds
     * @param int         $maxPerSessionPerTrip
     * @param int|null    $userId  Nếu có đăng nhập
     * @return array      Kết quả JSON-friendly
     */
    public function checkout(
        array $trips,
        string $token,
        int $ttlSeconds = self::DEFAULT_TTL,
        int $maxPerSessionPerTrip = self::MAX_PER_SESSION_PER_TRIP,
        ?int $userId = null
    ): array {
        // 0) Chuẩn hoá input
        [$pairs, $seatsByTrip] = $this->normalizeTrips($trips);
        if (empty($pairs)) {
            return [
                'success' => false,
                'code'    => 'EMPTY_REQUEST',
                'message' => 'Không có ghế hợp lệ để lock.',
            ];
        }

        // 1) (Khuyến nghị) Chặn ghế đã booked trong DB cho từng trip trước khi vào Redis
        foreach ($seatsByTrip as $tid => $sids) {
            $booked = \App\Models\TripSeatStatus::where('trip_id', $tid)
                ->whereIn('seat_id', $sids)
                ->where('is_booked', true)
                ->pluck('seat_id')
                ->all();
            if (!empty($booked)) {
                return [
                    'success' => false,
                    'code'    => 'ALREADY_BOOKED',
                    'message' => 'Một hoặc nhiều ghế đã được đặt (booked).',
                    'meta'    => ['trip_id' => $tid, 'seat_ids' => $booked],
                ];
            }
        }

        // 2) Build toàn bộ KEY ghế
        $seatKeys = [];
        foreach ($pairs as $p) {
            $seatKeys[] = $this->kSeatLock($p['trip_id'], $p['seat_id']);
        }

        // 3) Lua atomic: lock batch đa-trip
        $this->ensureLuaLoaded();
        $ttl   = max(30, (int)$ttlSeconds);
        $redis = Redis::connection();

        // evalsha: [numKeys, ...KEYS, token, ttl, maxPerTrip]
        $res = $this->evalshaOrEval(
            $redis,
            $this->luaTryLockSha,
            $this->luaTryLockScript(),
            count($seatKeys),
            array_merge($seatKeys, [$token, (string)$ttl, (string)$maxPerSessionPerTrip])
        );

        // Lua trả về:
        //  {1, lockedCount}
        //  {0, failedKey}
        //  {-1, tripId, limit, current, add}
        //  {-2, "BAD_KEY", key}
        $flag = (int)($res[0] ?? 0);

        if ($flag === -1) {
            $tripId = (int)($res[1] ?? 0);
            $limit  = (int)($res[2] ?? 0);
            $current = (int)($res[3] ?? 0);
            $add    = (int)($res[4] ?? 0);
            return [
                'success' => false,
                'code'    => 'PER_TRIP_LIMIT',
                'message' => "Vượt quá giới hạn {$limit} ghế cho trip {$tripId} trong một phiên.",
                'meta'    => compact('tripId', 'limit', 'current', 'add'),
            ];
        }

        if ($flag === 0) {
            $failedKey = (string)($res[1] ?? '');
            $label = $this->seatLabelFromKey($failedKey);
            return [
                'success' => false,
                'code'    => 'SEAT_LOCKED_BY_OTHERS',
                'message' => "Ghế {$label} đang được người khác giữ/đặt. Vui lòng chọn ghế khác.",
                'meta'    => ['failed_key' => $failedKey],
            ];
        }

        if ($flag !== 1) {
            return [
                'success' => false,
                'code'    => 'UNKNOWN',
                'message' => 'Không thể lock ghế (lỗi không xác định).',
                'meta'    => ['lua' => $res],
            ];
        }

        // 4) Tạo draft cho toàn bộ ghế vừa lock
        $draft = $this->drafts->createFromSeatPairs(
            userId: $userId,
            sessionToken: $token,
            pairs: $pairs,             // [['trip_id','seat_id','leg?'],...]
            ttlSeconds: $ttlSeconds
        );

        return [
            'success'      => true,
            'status'       => 'pending',
            'draft_id'     => $draft->id,
            'expires_at'   => optional($draft->expires_at)->toIso8601String(),
            'seconds_left' => $ttlSeconds,
            'items'        => $draft->items->map(fn($it) => [
                'trip_id'    => $it->trip_id,
                'seat_id'    => $it->seat_id,
                'seat_label' => $it->seat_label,
                'price'      => (int)$it->price,
                'leg'        => $it->leg,
            ])->values(),
        ];
    }

    /**
     * Dùng trước khi finalize 1 trip: khẳng định tất cả seat vẫn đang lock bởi token.
     */
    public function assertSeatsLockedByToken(int $tripId, array $seatIds, string $token): void
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        foreach ($seatIds as $sid) {
            $k = $this->kSeatLock($tripId, $sid);
            $owner = Redis::get($k);
            if ($owner !== $token) {
                throw new RuntimeException("Seat {$sid} (trip {$tripId}) không còn lock bởi token.");
            }
        }
    }

    /**
     * Dùng trước khi finalize multi-trip: assert theo map [tripId => [seatIds..]].
     */
    public function assertMultiLockedByToken(array $tripSeatMap, string $token): void
    {
        foreach ($tripSeatMap as $tid => $sids) {
            $this->assertSeatsLockedByToken((int)$tid, (array)$sids, $token);
        }
    }

    /**
     * Nhả ghế theo token cho danh sách trip (dùng khi hủy/timeout).
     * @return int Số ghế đã DEL
     */
    public function releaseByToken(array $tripIds, string $token): int
    {
        $this->ensureLuaLoaded();
        $tripIds = array_values(array_unique(array_map('intval', $tripIds)));

        // KEYS = [ trip:{tid}:locked_by:{token}, ... ]
        $setKeys = array_map(fn($tid) => $this->kTripLockedByToken($tid, $token), $tripIds);

        $res = $this->evalshaOrEval(
            Redis::connection(),
            $this->luaReleaseSha,
            $this->luaReleaseScript(),
            count($setKeys),
            array_merge($setKeys, [$token])
        );

        return (int)($res[0] ?? 0);
    }

    /* ============================================================
     |  KEY BUILDERS
     * ============================================================
     */

    private function kSeatLock(int $tripId, int $seatId): string
    {
        // Giữ format này vì Lua parse theo pattern này
        return "trip:{$tripId}:seat:{$seatId}:lock";
    }

    private function kTripLockedByToken(int $tripId, string $token): string
    {
        return "trip:{$tripId}:locked_by:{$token}";
    }

    private function kSessionSet(int $tripId, string $token): string
    {
        return "trip:{$tripId}:sess:{$token}:s";
    }

    private function kTripSet(int $tripId): string
    {
        return "trip:{$tripId}:locks:s";
    }

    /* ============================================================
     |  HELPERS
     * ============================================================
     */

    private function normalizeSeatIds(array $seatIds): array
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        return array_values(array_filter($seatIds, fn($v) => $v > 0));
    }

    /**
     * @return array{0: array<int,array{trip_id:int,seat_id:int,leg?:string|null}>, 1: array<int,array<int>>}
     */
    private function normalizeTrips(array $trips): array
    {
        $pairs = [];       // [['trip_id'=>101,'seat_id'=>12,'leg'=>'OUT'], ...]
        $byTrip = [];      // [101 => [12,13], 202 => [5], ...]

        foreach ($trips as $t) {
            $tid  = (int)($t['trip_id'] ?? 0);
            $sids = $this->normalizeSeatIds($t['seat_ids'] ?? []);
            $leg  = isset($t['leg']) ? (string)$t['leg'] : null;
            if ($tid <= 0 || empty($sids)) continue;

            foreach ($sids as $sid) {
                $pairs[] = ['trip_id' => $tid, 'seat_id' => $sid, 'leg' => $leg];
            }
            $byTrip[$tid] = $sids;
        }

        // (option) sắp xếp theo trip_id để giảm deadlock giữa nhiều client
        usort($pairs, fn($a, $b) => $a['trip_id'] <=> $b['trip_id']);

        return [$pairs, $byTrip];
    }

    private function seatLabelFromKey(string $key): string
    {
        // Nếu có map seat_id→label trong DB thì thay thế logic này
        if (preg_match('/seat:(\d+):lock$/', $key, $m)) {
            return 'ID ' . $m[1];
        }
        return 'N/A';
    }

    /* ============================================================
     |  LUA LOADER + EVAL HELPERS
     * ============================================================
     */

    private function ensureLuaLoaded(): void
    {
        if (!$this->luaTryLockSha) {
            $this->luaTryLockSha = (string) Redis::script('load', $this->luaTryLockScript());
        }
        if (!$this->luaReleaseSha) {
            $this->luaReleaseSha = (string) Redis::script('load', $this->luaReleaseScript());
        }
    }

    /**
     * Tương thích cả phpredis/predis: ưu tiên evalsha, fallback sang eval nếu sha chưa có.
     *
     * @param \Illuminate\Redis\Connections\Connection $redis
     * @param string|null $sha
     * @param string      $script
     * @param int         $numKeys
     * @param array       $keysAndArgs
     * @return mixed
     */
    private function evalshaOrEval($redis, ?string $sha, string $script, int $numKeys, array $keysAndArgs)
    {
        try {
            // Predis: evalsha($sha, $numKeys, ...$params)
            return $redis->evalsha($sha, $numKeys, ...$keysAndArgs);
        } catch (\Throwable $e) {
            // Fallback: eval($script, $numKeys, ...$params)
            return $redis->eval($script, $numKeys, ...$keysAndArgs);
        }
    }

    /**
     * LUA: LOCK MULTI-TRIP (atomic)
     * KEYS = [ trip:{tid}:seat:{sid}:lock, ... ]
     * ARGV = [ token, ttlSeconds, maxPerTrip ]
     * Return:
     *  {1, lockedCount}
     *  {0, failedKey}
     *  {-1, tripId, limit, current, add}
     *  {-2, "BAD_KEY", key}
     */
    private function luaTryLockScript(): string
    {
        return <<< 'LUA'
local token  = ARGV[1]
local ttlSec = tonumber(ARGV[2]) or 180
local maxPer = tonumber(ARGV[3]) or 6
local ttlMs  = ttlSec * 1000

local function parseTripId(key)
  local tid = string.match(key, "^trip:(%d+):seat:")
  return tonumber(tid or "0")
end
local function parseSeatId(key)
  local sid = string.match(key, "^trip:%d+:seat:(%d+):lock$")
  return tonumber(sid or "0")
end
local function sessSetKey(tid)
  return "trip:"..tid..":sess:"..token..":s"
end
local function tripSetKey(tid)
  return "trip:"..tid..":locks:s"
end
local addCount = {}  -- tid -> count
local trips = {}

-- 0) gom ghế dự định lock theo từng trip & check key format
for i=1, #KEYS do
  local key = KEYS[i]
  local tid = parseTripId(key)
  if not (tid and tid>0) then
    return {-2, "BAD_KEY", key}
  end
  if not addCount[tid] then
    addCount[tid] = 0
    table.insert(trips, tid)
  end
  addCount[tid] = addCount[tid] + 1
end

-- 1) quota per trip (trước khi lock)
for _, tid in ipairs(trips) do
  local sess = sessSetKey(tid)
  local cur  = tonumber(redis.call("SCARD", sess)) or 0
  local need = addCount[tid]
  if (cur + need) > maxPer then
    return {-1, tid, maxPer, cur, need}
  end
end

-- 2) detect conflict trước (đỡ rollback)
for i=1, #KEYS do
  local key   = KEYS[i]
  local owner = redis.call("GET", key)
  if owner and owner ~= token then
    return {0, key}
  end
end

-- 3) acquire + index
for i=1, #KEYS do
  local key = KEYS[i]
  local ok  = redis.call("SET", key, token, "NX", "PX", ttlMs)
  if not ok then
    return {0, key}
  end

  local tid = parseTripId(key)
  local sid = parseSeatId(key)
  if tid and tid>0 and sid and sid>0 then
    local tset = tripSetKey(tid)
    local sset = sessSetKey(tid)
    redis.call("SADD", tset, sid)
    redis.call("SADD", sset, sid)
    redis.call("PEXPIRE", sset, ttlMs)
  end
end

return {1, #KEYS}
LUA;
    }

    /**
     * LUA: RELEASE by token-set per trip
     * KEYS = [ trip:{tid}:locked_by:{token}, ... ]  // hoặc dùng sess:{token}:s nếu bạn muốn
     * ARGV = [ token ]
     * Return: { releasedCount }
     */
    private function luaReleaseScript(): string
    {
        return <<< 'LUA'
local token = ARGV[1]
local released = 0

for i=1, #KEYS do
  local setKey = KEYS[i]
  local members = redis.call("SMEMBERS", setKey)
  for j=1, #members do
    local sid = tonumber(members[j]) or 0
    if sid>0 then
      local seatKey = string.gsub(setKey, ":locked_by:"..token.."$", ":seat:"..sid..":lock")
      seatKey = string.gsub(seatKey, ":locks:s$", ":seat:"..sid..":lock") -- phòng trường hợp build từ tripSet
      local owner = redis.call("GET", seatKey)
      if owner == token then
        redis.call("DEL", seatKey)
        released = released + 1
      end
    end
  end
  redis.call("DEL", setKey)
end

return {released}
LUA;
    }
}
