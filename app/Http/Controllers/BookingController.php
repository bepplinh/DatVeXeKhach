<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Http\Requests\Booking\BookSeatsRequest;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    /**
     * Đặt ghế - chỉ được gọi sau khi user đã chọn ghế thành công
     */
    public function store(BookSeatsRequest $request, int $tripId)
    {
        try {
            $codes = $this->service->book(
                $tripId,
                $request->seat_ids,
                $request->user()->id,
                $request->coupon_code ?? null
            );
            
            return response()->json([
                'success' => true, 
                'message' => 'Đặt ghế thành công',
                'data' => [
                    'codes' => $codes,
                    'trip_id' => $tripId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Lấy danh sách ghế đang chọn của user
     */
    public function getUserSelections(Request $request, int $tripId)
    {
        try {
            $selections = $this->service->getUserSelectedSeats(
                $request->user()->id,
                $tripId
            );

            return response()->json([
                'success' => true,
                'data' => $selections
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Hủy tất cả ghế đang chọn của user
     */
    public function cancelSelections(Request $request, int $tripId)
    {
        try {
            $this->service->cancelUserSelections(
                $request->user()->id,
                $tripId
            );

            return response()->json([
                'success' => true,
                'message' => 'Hủy tất cả ghế đang chọn thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
