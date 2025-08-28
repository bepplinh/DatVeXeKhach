<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SeatSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeatSelectionController extends Controller
{
    public function __construct(private SeatSelectionService $seatSelectionService) {}

    /**
     * Chọn ghế
     */
    public function selectSeats(Request $request, int $tripId)
    {
        $request->validate([
            'seat_ids' => 'required|array',
            'seat_ids.*' => 'integer|exists:seats,id'
        ]);

        try {
            $sessionToken = $request->header('X-Session-Token') ?? Str::random(32);
            
            $result = $this->seatSelectionService->selectSeats(
                $tripId,
                $request->seat_ids,
                $request->user()->id,
                $sessionToken
            );

            return response()->json([
                'success' => true,
                'message' => 'Chọn ghế thành công',
                'data' => $result,
                'session_token' => $sessionToken
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Hủy chọn ghế
     */
    public function unselectSeats(Request $request, int $tripId)
    {
        $request->validate([
            'seat_ids' => 'required|array',
            'seat_ids.*' => 'integer|exists:seats,id'
        ]);

        try {
            $sessionToken = $request->header('X-Session-Token') ?? Str::random(32);

            $this->seatSelectionService->unselectSeats(
                $tripId,
                $request->seat_ids,
                $request->user()->id,
                $sessionToken
            );

            return response()->json([
                'success' => true,
                'message' => 'Hủy chọn ghế thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Hủy tất cả ghế đang chọn
     */
    public function unselectAllSeats(Request $request, int $tripId)
    {
        try {
            $this->seatSelectionService->unselectAllUserSeats(
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

    /**
     * Lấy danh sách ghế đang chọn của user
     */
    public function getUserSelections(Request $request, int $tripId)
    {
        try {
            $selections = $this->seatSelectionService->getUserSelections(
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
     * Kiểm tra trạng thái ghế
     */
    public function checkSeatsStatus(Request $request, int $tripId)
    {
        $request->validate([
            'seat_ids' => 'required|array',
            'seat_ids.*' => 'integer|exists:seats,id'
        ]);

        try {
            $availableSeats = $this->seatSelectionService->checkSeatsAvailability(
                $tripId,
                $request->seat_ids
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'available_seats' => $availableSeats,
                    'unavailable_seats' => array_diff($request->seat_ids, $availableSeats)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
