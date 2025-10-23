<?php

namespace App\Services;

use App\Events\SeatBooked;
use App\Events\SeatLocked;
use App\Events\SeatSelecting;

use App\Models\DraftCheckout;
use App\Models\TripSeatStatus;
use Illuminate\Support\Carbon;
use App\Events\SeatUnselecting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Services\DraftCheckoutService\DraftCheckoutService;

class SeatFlowService
{
    public const DEFAULT_TTL = 30; // 3 phút

    public function __construct(
        private DraftCheckoutService $drafts,
    ) {}

    /* ======================= Helpers / Key builders ======================= */

    private function normalizeSeatIds(array $seatIds): array
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        return array_values(array_filter($seatIds, fn($id) => $id > 0));
    }

    private function seatKey(int $tripId, int $seatId): string
    {
        return "trip:{$tripId}:seat:{$seatId}:lock";
    }

    // dùng sprintf để khỏi lỗi khi { $seatId } chứa khoảng trắng
    private function seatKeyFmt(int $tripId, int $seatId): string
    {
        return sprintf("trip:%d:seat:%d:lock", $tripId, $seatId);
    }

    private function zKey(int $tripId): string
    {
        return "trip:{$tripId}:locks:z";
    }

    private function tripSetKey(int $tripId): string
    {
        return "trip:{$tripId}:locks:s";
    }

    private function sessionSetKey(int $tripId, string $token): string
    {
        return "trip:{$tripId}:sess:{$token}:s";
    }

    /** Eval Lua: chạy được cả PhpRedis & Predis */
    private function evalLua(string $lua, array $keys, array $args): mixed
    {
        $conn = Redis::connection();
        // PhpRedis:
        if (method_exists($conn, 'client') && $conn->client() instanceof Redis) {
            $merged = array_merge($keys, $args);
            /** @var \Redis $cli */
            $cli = $conn->client();
            return $cli->eval($lua, $merged, count($keys));
        }
        // Predis:
        return $conn->eval($lua, count($keys), ...$keys, ...$args);
    }

    /* ========================== Soft select (UI) ========================== */

    public function select(int $tripId, array $seatIds, int $userId, int $hintTtl = 30): void
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) return;

        broadcast(new SeatSelecting($tripId, $seatIds, $userId, $hintTtl))->toOthers();
    }

    public function unselect(int $tripId, array $seatIds, int $userId): void
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) return;

        broadcast(new SeatUnselecting($tripId, $seatIds, $userId))->toOthers();
    }

    /* ============================ Hard lock =============================== */

    /**
     * Checkout (all-or-nothing, atomic bằng Lua)
     * - Giới hạn số ghế/phiên bằng SCARD(sessSet)
     * - Nếu có xung đột => fail toàn bộ
     * - Nếu ok => SET/EXPIRE từng ghế + ZADD + SADD (tripSet & sessSet)
     */
    public function checkout(
        int $tripId,
        array $seatIds,
        string $token,
        int $ttlSeconds = self::DEFAULT_TTL,
        int $maxPerSession = 6,
        int $userId = 0,
        ?int $fromLocationId = null,
        ?int $toLocationId = null
    ): array {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) {
            return ['locked' => [], 'failed' => [], 'lock_expires_at' => null, 'all_or_nothing' => true];
        }

        // Chặn ghế đã booked trong DB trước khi vào Redis
        $booked = TripSeatStatus::where('trip_id', $tripId)
            ->whereIn('seat_id', $seatIds)
            ->where('is_booked', true)
            ->pluck('seat_id')
            ->all();

        if (!empty($booked)) {
            return [
                'locked' => [],
                'failed' => $seatIds,
                'lock_expires_at' => null,
                'all_or_nothing' => true,
                'message' => 'Một hoặc nhiều ghế đã được đặt (booked).'
            ];
        }

        $ttl   = max(30, (int)$ttlSeconds);
        $nowTs = time();
        $expTs = $nowTs + $ttl;

        $zKey     = $this->zKey($tripId);
        $tripSet  = $this->tripSetKey($tripId);
        $sessSet  = $this->sessionSetKey($tripId, $token);

        // KEYS: [ zKey, tripSet, sessSet, seatKey1.. ]
        $keys = [$zKey, $tripSet, $sessSet];
        foreach ($seatIds as $sid) {
            $keys[] = $this->seatKeyFmt($tripId, $sid);
        }

        // ARGV: [ token, ttl, expTs, maxPerSession, reqCnt, seatId1.. ]
        $argv = array_merge(
            [$token, (string)$ttl, (string)$expTs, (string)$maxPerSession, (string)count($seatIds)],
            array_map('strval', $seatIds)
        );

        $lua = <<<LUA
-- KEYS[1]=zKey, KEYS[2]=tripSet, KEYS[3]=sessSet, KEYS[4..]=seatKeys
-- ARGV[1]=token, [2]=ttl, [3]=expTs, [4]=maxPerSession, [5]=reqCnt, [6..]=seatIds
local zkey    = KEYS[1]
local tripSet = KEYS[2]
local sessSet = KEYS[3]

local token   = ARGV[1]
local ttl     = tonumber(ARGV[2])
local expTs   = tonumber(ARGV[3])
local maxPer  = tonumber(ARGV[4])
local reqCnt  = tonumber(ARGV[5])

-- 0) Limit ghế/phiên
local cur = redis.call("SCARD", sessSet) or 0
if (cur + reqCnt) > maxPer then
  return {-1, {}}
end

-- 1) Phát hiện xung đột
local failedIdx = {}
for i = 4, #KEYS do
  local key = KEYS[i]
  local owner = redis.call("GET", key)
  if owner and owner ~= token then
    table.insert(failedIdx, i - 3) -- i=4 ↔ seatIds[1]
  end
end
if #failedIdx > 0 then
  return {0, failedIdx}
end

-- 2) Acquire/Renew + cập nhật chỉ mục
for i = 4, #KEYS do
  local key    = KEYS[i]
  local owner  = redis.call("GET", key)
  local seatId = tonumber(ARGV[2 + i]) -- i=4 -> ARGV[6]

  if not owner then
    redis.call("SET", key, token, "EX", ttl, "NX")
  else
    redis.call("EXPIRE", key, ttl)
  end

  redis.call("ZADD", zkey, expTs, seatId)
  redis.call("SADD", tripSet, seatId)
  redis.call("SADD", sessSet, seatId)
end

-- kéo dài tuổi thọ sessSet để tự dọn
redis.call("EXPIRE", sessSet, ttl * 2)

return {1, {}}
LUA;

        [$status, $failedIdx] = $this->evalLua($lua, $keys, $argv);

        if ((int)$status === -1) {
            return [
                'locked' => [],
                'failed' => $seatIds,
                'lock_expires_at' => null,
                'all_or_nothing' => true,
                'message' => "Tối đa {$maxPerSession} ghế cho mỗi phiên."
            ];
        }

        if ((int)$status !== 1) {
            $failed = [];
            foreach ($failedIdx as $i) {
                $failed[] = $seatIds[$i - 1] ?? null;
            }
            $failed = array_values(array_filter($failed, fn($x) => $x !== null));
            return [
                'locked' => [],
                'failed' => $failed ?: $seatIds,
                'lock_expires_at' => null,
                'all_or_nothing' => true,
                'message' => 'Một hoặc nhiều ghế đang được người khác giữ.'
            ];
        }

        // lock OK → tạo/ghi draft
        $draft = $this->drafts->startFromLockedSeats(
            tripId: $tripId,
            seatIds: $seatIds,
            sessionToken: $token,
            userId: $userId ?: null,
            ttlSeconds: $ttlSeconds,
            fromLocationId: $fromLocationId,
            toLocationId: $toLocationId
        );

        // Thành công: broadcast realtime
        if (!empty($seatIds)) {
            broadcast(new SeatLocked($tripId, $seatIds, $token, $ttl, $userId))->toOthers();
        }

        return [
            'locked' => $seatIds,
            'failed' => [],
            'lock_expires_at' => now()->addSeconds($ttl)->toISOString(),
            'all_or_nothing'  => true,
        ];
    }

    /**
     * Release (atomic bằng Lua)
     * - Chỉ ghế do đúng token đang giữ mới được thả
     * - Xóa key ghế + ZREM + SREM (tripSet & sessSet)
     */
    public function release(int $tripId, array $seatIds, string $token, int $userId): array
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) return ['released' => [], 'failed' => []];

        $zKey    = $this->zKey($tripId);
        $tripSet = $this->tripSetKey($tripId);
        $sessSet = $this->sessionSetKey($tripId, $token);

        // KEYS: [ zKey, tripSet, sessSet, seatKey1.. ]
        $keys = [$zKey, $tripSet, $sessSet];
        foreach ($seatIds as $sid) {
            $keys[] = $this->seatKeyFmt($tripId, $sid);
        }

        // ARGV: [ token, seatId1.. ]
        $argv = array_merge([$token], array_map('strval', $seatIds));

        $lua = <<<LUA
-- KEYS[1]=zKey, KEYS[2]=tripSet, KEYS[3]=sessSet, KEYS[4..]=seatKeys
-- ARGV[1]=token, ARGV[2..]=seatIds
local zkey    = KEYS[1]
local tripSet = KEYS[2]
local sessSet = KEYS[3]
local token   = ARGV[1]

local released = {}
local failed   = {}

for i = 4, #KEYS do
  local key    = KEYS[i]
  local seatId = tonumber(ARGV[i - 2]) -- i=4 -> ARGV[2]
  local owner  = redis.call("GET", key)

  if owner and owner == token then
    redis.call("DEL", key)
    redis.call("ZREM", zkey, seatId)
    redis.call("SREM", tripSet, seatId)
    redis.call("SREM", sessSet, seatId)
    table.insert(released, seatId)
  else
    table.insert(failed, seatId)
  end
end

return {released, failed}
LUA;

        [$released, $failed] = $this->evalLua($lua, $keys, $argv);

        $released = array_map('intval', $released ?? []);
        $failed   = array_map('intval', $failed ?? []);

        if (!empty($released)) {
            broadcast(new SeatUnselecting($tripId, $released, $userId))->toOthers();
        }

        return ['released' => $released, 'failed' => $failed];
    }

    /* ============================ Booked ============================== */

    /**
     * Mark booked sau khi đã thanh toán thành công.
     * - Xác thực: mọi seat phải đang do token giữ và TTL > 0
     * - Ghi DB (transaction)
     * - Giải phóng toàn bộ khóa bằng Lua (atomic)
     */
    public function markSeatsAsBooked(int $tripId, array $seatIds, int $userId)
    {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (empty($seatIds)) {
            throw new \RuntimeException('No seats to book.');
        }

        $now = now();

        return DB::transaction(function () use ($tripId, $seatIds, $userId, $now) {
            // 1) Seed rows if missing to satisfy unique(trip_id, seat_id)
            $seed = [];
            foreach ($seatIds as $sid) {
                $seed[] = [
                    'trip_id' => $tripId,
                    'seat_id' => $sid,
                    'is_booked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('trip_seat_statuses')->insertOrIgnore($seed);

            // 2) Update to booked only when currently not booked
            $in     = implode(',', array_fill(0, count($seatIds), '?'));
            $params = array_merge([$userId, $now, $now, $tripId], $seatIds);
            $affected = DB::update("
                UPDATE trip_seat_statuses
                SET booked_by=?, booked_at=?, is_booked=1, updated_at=?
                WHERE trip_id=? AND seat_id IN ($in) AND is_booked=0
            ", $params);

            if ($affected !== count($seatIds)) {
                throw new \RuntimeException('Some seats are not available to book anymore.');
            }

            return $affected;
        });
    }

    /**
     * Confirm booked based on an existing DraftCheckout and created Booking.
     * Used after payment success (e.g., webhook).
     */
    public function confirmBooked(DraftCheckout $draft, int $bookingId, ?int $userId = null): void
    {
        $tripId = (int) $draft->trip_id;
        $token  = (string) $draft->session_token;
        $userId = (int) ($userId ?? $draft->user_id ?? 0);
        $seatIds = $draft->items()->pluck('seat_id')->map(fn($v) => (int)$v)->all();
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (empty($seatIds)) return;

        // Ensure all locks are still owned by this session (best effort)
        foreach ($seatIds as $sid) {
            $key = $this->seatKeyFmt($tripId, $sid);
            if (Redis::get($key) !== $token) {
                // Continue anyway; DB status will be the source of truth
                // and locks will be cleaned below.
                break;
            }
        }

        $now = Carbon::now();

        DB::transaction(function () use ($tripId, $seatIds, $now) {
            // Seed rows nếu thiếu
            $seed = [];
            foreach ($seatIds as $sid) {
                $seed[] = [
                    'trip_id' => $tripId,
                    'seat_id' => $sid,
                    'is_booked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('trip_seat_statuses')->insertOrIgnore($seed);

            // Đặt booked
            $in     = implode(',', array_fill(0, count($seatIds), '?'));
            $params = array_merge([$now, $now, $tripId], $seatIds);

            $affected = DB::update("
                UPDATE trip_seat_statuses
                SET is_booked=1, booked_at=?, updated_at=?
                WHERE trip_id=? AND seat_id IN ($in) AND is_booked=0
            ", $params);

            if ($affected !== count($seatIds)) {
                throw new \RuntimeException('Some seats are not available to book anymore.');
            }
        });

        // Release locks
        $zKey    = $this->zKey($tripId);
        $tripSet = $this->tripSetKey($tripId);
        $sessSet = $this->sessionSetKey($tripId, $token);

        $keys = [$zKey, $tripSet, $sessSet];
        foreach ($seatIds as $sid) {
            $keys[] = $this->seatKeyFmt($tripId, $sid);
        }
        $argv = array_merge([$token], array_map('strval', $seatIds));

        $lua = <<<LUA
-- KEYS[1]=zKey, KEYS[2]=tripSet, KEYS[3]=sessSet, KEYS[4..]=seatKeys
-- ARGV[1]=token, ARGV[2..]=seatIds
local zkey    = KEYS[1]
local tripSet = KEYS[2]
local sessSet = KEYS[3]
local token   = ARGV[1]

for i = 4, #KEYS do
  local key    = KEYS[i]
  local seatId = tonumber(ARGV[i - 2])
  local owner  = redis.call("GET", key)
  if owner and owner == token then
    redis.call("DEL", key)
    redis.call("ZREM", zkey, seatId)
    redis.call("SREM", tripSet, seatId)
    redis.call("SREM", sessSet, seatId)
  end
end

return 1
LUA;

        $this->evalLua($lua, $keys, $argv);

        // Broadcast event (tạm thời comment để test)
        // broadcast(new SeatBooked($tripId, $seatIds, $bookingId, $userId))->toOthers();
    }

    /* ======================== Load trạng thái ghế ======================== */

    /**
     * Trả về: seatId => { status: available|locked|booked, ttl: ?int }
     * - DB: status 'booked'
     * - Redis: seat key (TTL) là nguồn sự thật cho locked
     * - Đồng thời dọn rác set trip nếu TTL <= 0
     */
    public function loadStatus(int $tripId, array $allSeatIds): array
    {
        $allSeatIds = $this->normalizeSeatIds($allSeatIds);
        if (!$allSeatIds) return [];

        // 1) Booked từ DB
        $booked = DB::table('trip_seat_statuses')
            ->where('trip_id', $tripId)
            ->where('status', 'booked')
            ->pluck('seat_id')->all();
        $booked = array_map('intval', $booked);
        $bookedSet = array_flip($booked);

        // 2) Locked từ Redis: tra TTL từng ghế (nguồn chuẩn)
        //    Kèm dọn rác cho tripSet nếu TTL <= 0
        $tripSet = $this->tripSetKey($tripId);
        $lockedLiveTtl = [];

        $members = Redis::smembers($tripSet) ?: [];
        foreach ($members as $sid) {
            $sid = (int)$sid;
            $ttl = Redis::ttl($this->seatKeyFmt($tripId, $sid));
            if ($ttl > 0) {
                $lockedLiveTtl[$sid] = $ttl;
            } else {
                // dọn rác set nếu ghế không còn TTL
                Redis::srem($tripSet, $sid);
            }
        }

        // 3) Ghép kết quả
        $out = [];
        foreach ($allSeatIds as $sid) {
            if (isset($bookedSet[$sid])) {
                $out[$sid] = ['status' => 'booked', 'ttl' => null];
            } elseif (isset($lockedLiveTtl[$sid])) {
                $out[$sid] = ['status' => 'locked', 'ttl' => $lockedLiveTtl[$sid]];
            } else {
                $out[$sid] = ['status' => 'available', 'ttl' => null];
            }
        }

        return $out;
    }

    public function handleSeatExpired(int $tripId, int $seatId): void
    {
        $zKey    = $this->zKey($tripId);
        $tripSet = $this->tripSetKey($tripId);

        // Nếu bạn có set theo session thì có thể SREM tất cả sess-set khớp seat này.
        // Tối thiểu: xoá ghế khỏi ZSET và set tổng.
        Redis::pipeline(function ($pipe) use ($zKey, $tripSet, $seatId) {
            $pipe->zrem($zKey, (string)$seatId);
            $pipe->srem($tripSet, (string)$seatId);
        });

        // Phát realtime cho frontend biết ghế đã tự unlock do hết TTL
        broadcast(new \App\Events\SeatUnselecting($tripId, [$seatId], null))->toOthers();
    }

    public function renewLocksForPayment(DraftCheckout $draft, int $extendSec)
    {
        $tripId = (int) $draft->trip_id;
        $session = (string) $draft->session_token;
        $seatIds = (array) $draft->items()->pluck('seat_id')->map(fn($v) => (int)$v)->all();

        foreach ($seatIds as $sid) {
            $key = "trip:$tripId:seat:$sid:lock";
            $owner = Redis::get($key);
            if ($owner === $session) {
                Redis::expire($key, $extendSec);
            }
        }
    }

    public function assertSeatsLockedByToken(int $tripId, array $seatIds, string $token)
    {
        foreach ($seatIds as $seatId) {
            // Redis key kiểu: trip:{tripId}:seat:{seatId}:lock
            $key = "trip:{$tripId}:seat:{$seatId}:lock";
            $value = Redis::get($key);

            if (!$value) {
                throw new \RuntimeException("Ghế {$seatId} đã hết hạn giữ.");
            }

            // Mỗi lock lưu dưới dạng JSON hoặc token
            if ($value !== $token) {
                throw new \RuntimeException("Ghế {$seatId} đã bị người khác giữ/đặt.");
            }
        }
    }

    public function releaseLocksAfterBooked(int $tripId, array $seatIds): array
    {
        // 1. Chuẩn hóa ID ghế và kiểm tra mảng rỗng
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) {
            return [];
        }

        $zKey    = $this->zKey($tripId);      // Sorted Set Key (TTL)
        $tripSet = $this->tripSetKey($tripId); // Set Key (Danh sách ghế đang khóa của chuyến đi)

        // 2. Chuẩn bị KEYS cho LUA script: [ zKey, tripSet, seatKey1, seatKey2, ... ]
        $keys = [$zKey, $tripSet];
        foreach ($seatIds as $sid) {
            $keys[] = $this->seatKeyFmt($tripId, $sid);
        }

        // 3. Chuẩn bị ARGV cho LUA script: [ seatId1, seatId2, ... ]
        $argv = array_map('strval', $seatIds);

        $lua = <<<LUA
-- KEYS[1]=zKey (TTL Sorted Set)
-- KEYS[2]=tripSet (Trip Set)
-- KEYS[3..]=seatKeys (Khóa từng ghế)
-- ARGV[1..]=seatIds

local zkey    = KEYS[1]
local tripSet = KEYS[2]

local released = {}

-- Lặp qua từng khóa ghế (bắt đầu từ KEYS[3])
for i = 3, #KEYS do
    local key    = KEYS[i]
    -- Tính toán index của seatId tương ứng trong ARGV
    local seatId = tonumber(ARGV[i - 2]) 

    -- Xóa khóa seat key VÔ ĐIỀU KIỆN
    local deleted = redis.call("DEL", key)
    
    -- Nếu key bị xóa thành công (deleted == 1)
    if deleted == 1 then
        -- Xóa khỏi Sorted Set (TTL)
        redis.call("ZREM", zkey, seatId)
        
        -- Xóa khỏi Trip Set (danh sách ghế đang khóa)
        redis.call("SREM", tripSet, seatId) 
        
        table.insert(released, seatId)
    end
end

-- Trả về danh sách các ID ghế đã được gỡ khóa
return {released}
LUA;
        // 4. Thực thi script Lua atomically
        [$released] = $this->evalLua($lua, $keys, $argv);

        // 5. Chuyển kết quả về int
        return array_map('intval', $released ?? []);
    }
}
