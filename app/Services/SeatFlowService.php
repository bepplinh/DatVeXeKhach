<?php

namespace App\Services;

use App\Models\Seat;
use Illuminate\Support\Str;
use App\Events\SeatSelecting;
use Illuminate\Support\Carbon;
use App\Events\SeatUnselecting;
use Illuminate\Support\Facades\DB;

class SeatFlowService
{
    /**
     * Soft-select (tooltip) — chỉ phát event
     */
    public function select(int $tripId, array $seatIds, ?int $userId, int $hintTtl = 30): void
    {
        broadcast(new SeatSelecting($tripId, $seatIds, $userId, $hintTtl))->toOthers();
    }

    /**
     * Unselect (tooltip) — chỉ phát event
     */
    public function unselect(int $tripId, array $seatIds, ?int $userId): void
    {
        broadcast(new SeatUnselecting($tripId, $seatIds, $userId))->toOthers();
    }

    /**
     * Checkout: cố gắng lock ghế (hard-lock tạm thời)
     * - TTL mặc định 5 phút
     * - Điều kiện lock: chưa booked và lock đã hết hạn hoặc cùng token (idempotent)
     * - Trả về danh sách ghế lock thành công / thất bại
     */
    public function checkout(
        int $tripId,
        array $seatIds,
        int $userId,
        int $ttlSeconds = 300
    ): array {
        $now    = now();
        $expire = $now->copy()->addSeconds($ttlSeconds);

        // Chuẩn hoá danh sách ghế
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);

        $failed = [];
        $locked = [];

        DB::transaction(function () use ($tripId, $seatIds, $userId, $now, $expire, &$failed, &$locked) {
            // (1) Tạo bản ghi "vỏ" nếu chưa có — bỏ qua duplicate
            $rows = [];
            foreach ($seatIds as $sid) {
                $rows[] = [
                    'trip_id'    => $tripId,
                    'seat_id'    => $sid,
                    'is_booked'  => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('trip_seat_statuses')->insertOrIgnore($rows);

            // (2) Khoá hàng để kiểm tra nguyên tử
            $statusRows = DB::table('trip_seat_statuses')
                ->where('trip_id', $tripId)
                ->whereIn('seat_id', $seatIds)
                ->lockForUpdate()
                ->get(['seat_id','is_booked','locked_by_user_id','lock_expires_at']);

            $nowStr    = $now->toDateTimeString();
            $conflicts = [];

            foreach ($statusRows as $r) {
                $isLockedValid = $r->lock_expires_at && $r->lock_expires_at > $nowStr;
                $lockedByOther = $isLockedValid && ($r->locked_by_user_id !== null) && ((int)$r->locked_by_user_id !== $userId);

                // Conflict nếu: đã BOOKED hoặc đang LOCK bởi NGƯỜI KHÁC
                if ($r->is_booked || $lockedByOther) {
                    $conflicts[] = (int)$r->seat_id;
                }
            }

            // (3) Có conflict -> KHÔNG ghi gì, trả danh sách ghế vi phạm
            if (!empty($conflicts)) {
                $failed = $conflicts;
                return; // không UPDATE gì -> transaction kết thúc không thay đổi DB
            }

            // (4) Không conflict -> LOCK TẤT CẢ cho user hiện tại
            DB::table('trip_seat_statuses')
                ->where('trip_id', $tripId)
                ->whereIn('seat_id', $seatIds)
                ->update([
                    'locked_by_user_id'       => $userId,
                    'locked_at'       => $now,
                    'lock_expires_at' => $expire,
                    'updated_at'      => $now,
                ]);

            $locked = $seatIds; // all-or-nothing
        });

        return [
            'locked'          => $locked,                 // thành công => toàn bộ ghế
            'failed'          => $failed,                 // có conflict => danh sách ghế vi phạm
            'lock_expires_at' => $locked ? $expire->toISOString() : null,
            'all_or_nothing'  => true,
        ];
    }

    /**
     * Release lock: bỏ giữ chỗ (khi user hủy/back hoặc timeout job)
     * - Chỉ remove lock nếu: lock đã hết hạn hoặc chính token này (để tránh người khác phá lock)
     */
    public function release(int $tripId, array $seatIds, int $userId): array
    {
        // Chuẩn hóa input
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);
        if (empty($seatIds)) {
            return ['released' => [], 'failed' => []];
        }
    
        DB::transaction(function () use ($tripId, $seatIds, $userId, &$result) {
            // Atomic UPDATE: chỉ release những ghế do chính user này đang giữ
            $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
            $params = array_merge([$tripId], $seatIds, [$userId]);
    
            $affected = DB::update("
                UPDATE trip_seat_statuses
                SET locked_by_user_id = NULL,
                    locked_at         = NULL,
                    lock_expires_at   = NULL,
                    updated_at        = NOW()
                WHERE trip_id = ?
                  AND seat_id IN ($placeholders)
                  AND locked_by_user_id = ?
            ", $params);
    
            if ($affected === count($seatIds)) {
                $result = ['released' => $seatIds, 'failed' => []];
                return;
            }
    
            // Tìm ghế không release được (không thuộc user hoặc không còn lock)
            $failed = DB::table('trip_seat_statuses')
                ->where('trip_id', $tripId)
                ->whereIn('seat_id', $seatIds)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('locked_by_user_id')
                      ->orWhere('locked_by_user_id', '!=', $userId);
                })
                ->pluck('seat_id')->map(fn($v) => (int)$v)->all();
    
            $released = array_values(array_diff($seatIds, $failed));
    
            $result = ['released' => $released, 'failed' => array_values(array_unique($failed))];
        });
    
        return $result;
    }

    /**
     * Xác nhận Booked sau thanh toán:
     * - Set is_booked = 1, booked_by, booked_at
     * - Clear lock
     * - Phát SeatBooked
     */
    public function markBooked(int $tripId, array $seatIds, int $userId): array
    {
        // Chuẩn hóa input
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);
        if (empty($seatIds)) {
            return ['booked' => [], 'failed' => []];
        }
    
        DB::transaction(function () use ($tripId, $seatIds, $userId, &$result) {
            $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
            $params = array_merge([$tripId], $seatIds, [$userId]);
    
            // Atomic UPDATE: chỉ chốt ghế đang do user này giữ & còn hạn
            $affected = DB::update("
                UPDATE trip_seat_statuses
                SET is_booked         = 1,
                    locked_by_user_id = NULL,
                    locked_at         = NULL,
                    lock_expires_at   = NULL,
                    updated_at        = NOW()
                WHERE trip_id = ?
                  AND seat_id IN ($placeholders)
                  AND is_booked = 0
                  AND locked_by_user_id = ?
                  AND (lock_expires_at IS NULL OR lock_expires_at > NOW())
            ", $params);
    
            if ($affected === count($seatIds)) {
                $result = ['booked' => $seatIds, 'failed' => []];
                return;
            }
    
            // Truy vết ghế fail để trả về UI
            $failed = DB::table('trip_seat_statuses')
                ->where('trip_id', $tripId)
                ->whereIn('seat_id', $seatIds)
                ->where(function ($q) use ($userId) {
                    $q->where('is_booked', 1) // đã bán trước đó
                      ->orWhere('locked_by_user_id', '!=', $userId) // không phải user này giữ
                      ->orWhere(function ($s) { // hết hạn lock
                          $s->whereNotNull('lock_expires_at')
                            ->where('lock_expires_at', '<=', DB::raw('NOW()'));
                      });
                })
                ->pluck('seat_id')->map(fn($v) => (int)$v)->all();
    
            $booked = array_values(array_diff($seatIds, $failed));
    
            $result = ['booked' => $booked, 'failed' => array_values(array_unique($failed))];
        });
    
        return $result;
    }
    
}
