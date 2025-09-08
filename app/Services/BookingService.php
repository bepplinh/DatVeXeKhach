<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TripSeatStatus;
use App\Events\SeatBooked;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Response;

class BookingService
{
    public function __construct(private SeatSelectionService $seatSelectionService) {}

    /**
     * Đặt ghế - ai đặt trước thì được trước
     */
    public function book(int $tripId, array $seatIds, int $userId, ?string $couponCode = null)
    {
        // Kiểm tra xem các ghế có còn khả dụng không (không bị người khác đặt trước)
        if (!$this->checkSeatsStillAvailable($tripId, $seatIds)) {
            throw new \Exception('Một hoặc nhiều ghế đã có người khác đặt trước. Vui lòng chọn ghế khác.');
        }

        $codes = [];

        DB::beginTransaction();
        try {
            foreach ($seatIds as $seatId) {
                $code = Str::random(6);
                
                // Tạo booking
                $booking = Booking::create([
                    'trip_id' => $tripId,
                    'seat_id' => $seatId,
                    'user_id' => $userId,
                    'code' => $code,
                    'status' => 'pending',
                ]);

                // Cập nhật trạng thái ghế thành đã đặt
                $this->markSeatAsBooked($tripId, $seatId, $userId);

                $codes[] = $code;
                
                // Broadcast event
                event(new SeatBooked($tripId, $seatId, $booking->id, $userId));
            }

            // Hủy tất cả ghế đang chọn của user sau khi đặt thành công (nếu có)
            $this->seatSelectionService->unselectAllUserSeats($userId, $tripId);

            DB::commit();
            return $codes;

        } catch (QueryException $e) {
            DB::rollBack();
            // MySQL 1062 / Postgres 23505
            $sqlState = (string)($e->errorInfo[0] ?? '');
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062 || $sqlState === '23505') {
                abort(409, 'Một hoặc nhiều ghế đã có người đặt trước.');
            }
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Kiểm tra xem các ghế có còn khả dụng không
     */
    private function checkSeatsStillAvailable(int $tripId, array $seatIds): bool
    {
        foreach ($seatIds as $seatId) {
            $seatStatus = TripSeatStatus::where('trip_id', $tripId)
                ->where('seat_id', $seatId)
                ->first();

            // Nếu ghế đã được đặt
            if ($seatStatus && $seatStatus->is_booked) {
                return false;
            }
        }

        return true;
    }

    /**
     * Đánh dấu ghế đã được đặt
     */
    private function markSeatAsBooked(int $tripId, int $seatId, int $userId): void
    {
        TripSeatStatus::updateOrCreate(
            [
                'trip_id' => $tripId,
                'seat_id' => $seatId
            ],
            [
                'is_booked' => true,
                'booked_by' => $userId,
                'locked_by' => null,
                'lock_expires_at' => null
            ]
        );
    }

    /**
     * Lấy danh sách ghế đang chọn của user
     */
    public function getUserSelectedSeats(int $userId, int $tripId): ?array
    {
        return $this->seatSelectionService->getUserSelections($userId, $tripId);
    }

    /**
     * Hủy tất cả ghế đang chọn của user
     */
    public function cancelUserSelections(int $userId, int $tripId): bool
    {
        return $this->seatSelectionService->unselectAllUserSeats($userId, $tripId);
    }
}