<?php

namespace App\Services;

use App\Events\SeatBooked;
use App\Events\SeatLocked;
use Illuminate\Support\Str;
use App\Events\SeatSelecting;

use App\Models\DraftCheckout;
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
    private function evalLua(string $lua, array $keys, array $args): array
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
        int $userId = 0
    ): array {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) {
            return ['locked'=>[], 'failed'=>[], 'lock_expires_at'=>null, 'all_or_nothing'=>true];
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
                'locked'=>[], 'failed'=>$seatIds, 'lock_expires_at'=>null, 'all_or_nothing'=>true,
                'message'=>"Tối đa {$maxPerSession} ghế cho mỗi phiên."
            ];
        }

        if ((int)$status !== 1) {
            $failed = [];
            foreach ($failedIdx as $i) { $failed[] = $seatIds[$i - 1] ?? null; }
            $failed = array_values(array_filter($failed, fn($x)=>$x!==null));
            return [
                'locked'=>[], 'failed'=>$failed ?: $seatIds, 'lock_expires_at'=>null, 'all_or_nothing'=>true,
                'message'=>'Một hoặc nhiều ghế đang được người khác giữ.'
            ];
        }

        // lock OK → tạo/ghi draft
        $draft = $this->drafts->startFromLockedSeats(
            tripId: $tripId,
            seatIds: $seatIds,
            sessionToken: $token,
            userId: $userId ?: null,
            ttlSeconds: $ttlSeconds
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
        if (!$seatIds) return ['released'=>[], 'failed'=>[]];

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

        return ['released'=>$released, 'failed'=>$failed];
    }

    /* ============================ Booked ============================== */

    /**
     * Mark booked sau khi đã thanh toán thành công.
     * - Xác thực: mọi seat phải đang do token giữ và TTL > 0
     * - Ghi DB (transaction)
     * - Giải phóng toàn bộ khóa bằng Lua (atomic)
     */
    public function markBooked(
        int $tripId,
        array $seatIds,
        string $token,
        int $userId,
        ?string $idempotencyKey = null
    ): array {
        $seatIds = $this->normalizeSeatIds($seatIds);
        if (!$seatIds) return ['booked'=>[], 'failed'=>$seatIds];

        // Idempotency (nếu dùng)
        if ($idempotencyKey) {
            $existing = DB::table('bookings')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $bookedSeatIds = DB::table('booking_items')
                    ->where('booking_id', $existing->id)
                    ->pluck('seat_id')->map(fn($v)=>(int)$v)->all();

                return [
                    'booked' => $bookedSeatIds,
                    'failed' => array_values(array_diff($seatIds, $bookedSeatIds)),
                    'booking_id' => $existing->id,
                ];
            }
        }

        // Kiểm tra khoá Redis (đúng token + còn TTL)
        foreach ($seatIds as $sid) {
            $key = $this->seatKeyFmt($tripId, $sid);
            if (Redis::get($key) !== $token || Redis::ttl($key) <= 0) {
                return ['booked'=>[], 'failed'=>$seatIds];
            }
        }

        $now = Carbon::now();

        $result = DB::transaction(function () use ($tripId, $seatIds, $userId, $idempotencyKey, $now) {

            // Seed rows nếu thiếu
            $seed = [];
            foreach ($seatIds as $sid) {
                $seed[] = [
                    'trip_id' => $tripId,
                    'seat_id' => $sid,
                    'status'  => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('trip_seat_statuses')->insertOrIgnore($seed);

            // Đặt booked
            $in     = implode(',', array_fill(0, count($seatIds), '?'));
            $params = array_merge([$now, $tripId], $seatIds);

            $affected = DB::update("
                UPDATE trip_seat_statuses
                SET status='booked', updated_at=?
                WHERE trip_id=? AND seat_id IN ($in) AND status <> 'booked'
            ", $params);

            if ($affected !== count($seatIds)) {
                throw new \RuntimeException('Some seats are not available to book anymore.');
            }

            // Tạo booking
            $bookingId = DB::table('bookings')->insertGetId([
                'code'            => strtoupper(Str::random(10)),
                'trip_id'         => $tripId,
                'user_id'         => $userId,
                'total_price'     => 0, // TODO: tính giá
                'discount_amount' => 0,
                'status'          => 'paid', // hoặc 'pending'
                'idempotency_key' => $idempotencyKey,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $items = [];
            foreach ($seatIds as $sid) {
                $items[] = [
                    'booking_id'  => $bookingId,
                    'seat_id'     => $sid,
                    'price_final' => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
            DB::table('booking_items')->insert($items);

            return ['booking_id' => $bookingId];
        });

        // Giải phóng khóa bằng Lua (atomic): DEL key + ZREM + SREM(tập trip & sess)
        $zKey    = $this->zKey($tripId);
        $tripSet = $this->tripSetKey($tripId);
        $sessSet = $this->sessionSetKey($tripId, $token);

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

        broadcast(new SeatBooked($tripId, $seatIds, $result['booking_id'], $userId))->toOthers();

        return ['booked'=>$seatIds, 'failed'=>[], 'booking_id'=>$result['booking_id']];
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

}
