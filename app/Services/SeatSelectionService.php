<?php

namespace App\Services;

use App\Models\TripSeatStatus;
use App\Models\Seat;
use App\Models\Trip;
use App\Events\SeatSelecting;
use App\Events\SeatUnselecting;
use App\Events\SeatBooked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SeatSelectionService
{
    private const LOCK_DURATION = 30; // 30 giây để giữ ghế

    /**
     * Chọn một hoặc nhiều ghế cho user
     */
    public function selectSeats(int $tripId, array $seatIds, int $userId, string $sessionToken): array
    {
        // Kiểm tra xem các ghế có khả dụng không
        $availableSeats = $this->checkSeatsAvailability($tripId, $seatIds);
        if (empty($availableSeats)) {
            throw new \Exception('Tất cả ghế bạn muốn chọn đã không còn khả dụng.');
        }

        $selectedSeats = [];
        $failedSeats = [];

        DB::beginTransaction();
        try {
            foreach ($seatIds as $seatId) {
                if (in_array($seatId, $availableSeats)) {
                    // Khóa ghế cho user này
                    $this->lockSeat($tripId, $seatId, $userId, $sessionToken);
                    $selectedSeats[] = $seatId;

                    // Broadcast event
                    event(new SeatSelecting($tripId, $seatId, $sessionToken, $userId, self::LOCK_DURATION));
                } else {
                    $failedSeats[] = $seatId;
                }
            }

            if (empty($selectedSeats)) {
                DB::rollBack();
                throw new \Exception('Không thể chọn được ghế nào. Vui lòng thử lại.');
            }

            // Lưu thông tin chọn ghế vào cache
            $this->saveUserSelections($userId, $tripId, $selectedSeats, $sessionToken);

            DB::commit();
            return [
                'selected_seats' => $selectedSeats,
                'failed_seats' => $failedSeats,
                'lock_duration' => self::LOCK_DURATION
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Hủy chọn ghế
     */
    public function unselectSeats(int $tripId, array $seatIds, int $userId, string $sessionToken): bool
    {
        DB::beginTransaction();
        try {
            foreach ($seatIds as $seatId) {
                if ($this->isSeatLockedByUser($tripId, $seatId, $userId)) {
                    $this->unlockSeat($tripId, $seatId);
                    
                    // Broadcast event
                    event(new SeatUnselecting($tripId, $seatId, $sessionToken));
                }
            }

            // Cập nhật cache
            $this->removeUserSelections($userId, $tripId, $seatIds);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Hủy tất cả ghế đang chọn của user
     */
    public function unselectAllUserSeats(int $userId, int $tripId): bool
    {
        $selections = $this->getUserSelections($userId, $tripId);
        if (empty($selections)) {
            return true;
        }

        DB::beginTransaction();
        try {
            foreach ($selections['seat_ids'] as $seatId) {
                if ($this->isSeatLockedByUser($tripId, $seatId, $userId)) {
                    $this->unlockSeat($tripId, $seatId);
                    
                    // Broadcast event
                    event(new SeatUnselecting($tripId, $seatId, $selections['session_token']));
                }
            }

            // Xóa cache
            $this->clearUserSelections($userId, $tripId);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Kiểm tra tính khả dụng của các ghế
     */
    public function checkSeatsAvailability(int $tripId, array $seatIds): array
    {
        $availableSeats = [];

        foreach ($seatIds as $seatId) {
            if ($this->isSeatAvailable($tripId, $seatId)) {
                $availableSeats[] = $seatId;
            }
        }

        return $availableSeats;
    }

    /**
     * Kiểm tra xem ghế có khả dụng không
     */
    private function isSeatAvailable(int $tripId, int $seatId): bool
    {
        $seatStatus = TripSeatStatus::where('trip_id', $tripId)
            ->where('seat_id', $seatId)
            ->first();

        if (!$seatStatus) {
            return true; // Ghế chưa có trạng thái, có thể chọn
        }

        // Ghế đã được đặt
        if ($seatStatus->is_booked) {
            return false;
        }

        // Kiểm tra xem lock có hết hạn chưa
        if ($seatStatus->lock_expires_at && $seatStatus->lock_expires_at->isPast()) {
            // Lock đã hết hạn, xóa lock
            $seatStatus->update([
                'locked_by' => null,
                'lock_expires_at' => null
            ]);
            return true;
        }

        // Ghế đang bị khóa (có thể bởi user khác hoặc chính user này)
        return false;
    }

    /**
     * Kiểm tra xem ghế có bị khóa bởi user này không
     */
    private function isSeatLockedByUser(int $tripId, int $seatId, int $userId): bool
    {
        $seatStatus = TripSeatStatus::where('trip_id', $tripId)
            ->where('seat_id', $seatId)
            ->where('locked_by', $userId)
            ->first();

        return $seatStatus && 
               $seatStatus->lock_expires_at && 
               $seatStatus->lock_expires_at->isFuture();
    }

    /**
     * Khóa ghế cho user
     */
    private function lockSeat(int $tripId, int $seatId, int $userId, string $sessionToken): void
    {
        TripSeatStatus::updateOrCreate(
            [
                'trip_id' => $tripId,
                'seat_id' => $seatId
            ],
            [
                'locked_by' => $userId,
                'lock_expires_at' => Carbon::now()->addSeconds(self::LOCK_DURATION)
            ]
        );
    }

    /**
     * Mở khóa ghế
     */
    private function unlockSeat(int $tripId, int $seatId): void
    {
        TripSeatStatus::where('trip_id', $tripId)
            ->where('seat_id', $seatId)
            ->update([
                'locked_by' => null,
                'lock_expires_at' => null
            ]);
    }

    /**
     * Lưu thông tin chọn ghế của user vào cache
     */
    private function saveUserSelections(int $userId, int $tripId, array $seatIds, string $sessionToken): void
    {
        $cacheKey = "user_selections:{$userId}";
        $userSelections = Cache::get($cacheKey, []);

        $userSelections[$tripId] = [
            'seat_ids' => $seatIds,
            'session_token' => $sessionToken,
            'selected_at' => Carbon::now()->toISOString()
        ];

        Cache::put($cacheKey, $userSelections, Carbon::now()->addMinutes(30));
    }

    /**
     * Lấy thông tin chọn ghế của user
     */
    public function getUserSelections(int $userId, int $tripId): ?array
    {
        $cacheKey = "user_selections:{$userId}";
        $userSelections = Cache::get($cacheKey, []);

        return $userSelections[$tripId] ?? null;
    }

    /**
     * Xóa thông tin chọn ghế của user
     */
    private function removeUserSelections(int $userId, int $tripId, array $seatIds): void
    {
        $cacheKey = "user_selections:{$userId}";
        $userSelections = Cache::get($cacheKey, []);

        if (isset($userSelections[$tripId])) {
            $userSelections[$tripId]['seat_ids'] = array_diff(
                $userSelections[$tripId]['seat_ids'], 
                $seatIds
            );

            if (empty($userSelections[$tripId]['seat_ids'])) {
                unset($userSelections[$tripId]);
            }

            Cache::put($cacheKey, $userSelections, Carbon::now()->addMinutes(30));
        }
    }

    /**
     * Xóa tất cả thông tin chọn ghế của user ở một trip
     */
    private function clearUserSelections(int $userId, int $tripId): void
    {
        $cacheKey = "user_selections:{$userId}";
        $userSelections = Cache::get($cacheKey, []);

        unset($userSelections[$tripId]);

        Cache::put($cacheKey, $userSelections, Carbon::now()->addMinutes(30));
    }
}
