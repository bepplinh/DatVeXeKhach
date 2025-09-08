<?php

namespace App\Http\Controllers;

use App\Models\Seat;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\SeatFlowService;

class SeatFlowController extends Controller
{
    public function __construct(private SeatFlowService $svc) {}

    /** Soft-select (tooltip) — không ghi DB, vẫn yêu cầu login để tránh spam */
    public function select(Request $request, int $tripId)
    {
        $trip = Trip::where('id', $tripId)->first(['id', 'bus_id']);
        if (!$trip) return response()->json([
            'success'=> false,
            'message'=> 'Trip not found'
        ], 404);

        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => ['integer', Rule::exists('seats','id')->where(fn($q) => $q->where('bus_id', $trip->bus_id))],
            'hint_ttl'   => ['nullable','integer','min:5','max:180'],
        ]);

        $userId  = $request->user()->id; 
        $hintTtl = $data['hint_ttl'] ?? 30;

        // Phiên bản không còn token: broadcast theo userId
        $this->svc->select($tripId, $data['seat_ids'], $userId, $hintTtl);

        return response()->json(['success'=>true, 'message'=>'Selected broadcasted']);
    }

    /** Unselect (tooltip) — không ghi DB */
    public function unselect(Request $request, int $tripId)
    {
        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => ['integer','exists:seats,id'],
        ]);

        $userId = $request->user()->id;

        $this->svc->unselect($tripId, $data['seat_ids'], $userId);

        return response()->json(['success'=>true, 'message'=>'Unselected broadcasted']);
    }

    /** Checkout: cố gắng lock ghế (hard-lock) — JWT-only */
    public function checkout(Request $request, int $tripId)
    {
        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => ['integer','exists:seats,id'],
            'ttl'        => ['nullable','integer','min:60','max:900'],
        ]);
    
        // ĐÃ dùng JWT -> yêu cầu auth:api ở route
        $userId = $request->user()->id;
    
        $result = $this->svc->checkout(
            $tripId,
            $data['seat_ids'],
            $userId,
            $data['ttl'] ?? 300
        );

        $allIds = array_values(array_unique(array_merge($result['failed'] ?? [], $result['locked'] ?? [])));
        $seatMap = empty($allIds)
            ? collect()
            : Seat::whereIn('id', $allIds)->pluck('seat_number', 'id');

        if(!empty($result['failed'])) {
            $failedNumbers = collect($result['failed'])->map(fn($id) => $seatMap[$id] ?? (string)$id)->all();
            $list = implode(', ', $failedNumbers);

            return response()->json([
                'success' => false,
                'locked' => [],
                'failed' => $failedNumbers,
                'failed_ids' => $result['failed'],
                'message' => "Ghế {$list} đã có người đặt trước đó, vui lòng chọn ghế khác.",
            ], 409);
        }
    
        $lockedNumbers = collect($result['locked'])->map(fn($id) => $seatMap[$id] ?? (string)$id)->all();

        return response()->json([
            'success'         => true,
            'locked'          => $lockedNumbers,          // ví dụ ["A1","A2"]
            'locked_ids'      => $result['locked'],       // (tùy chọn) id nguyên bản
            'failed'          => [],
            'lock_expires_at' => $result['lock_expires_at'],
            'message'         => 'Đã giữ chỗ cho tất cả ghế, tiếp tục thanh toán.',
        ]);
    }

    /** Release lock: bỏ giữ chỗ (user hủy/back) — JWT-only */
    public function release(Request $request, int $tripId)
    {
        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => ['integer','exists:seats,id'],
        ]);

        $userId = $request->user()->id;

        // LƯU Ý: service.release KHÔNG còn tham số token; dùng userId
        $result = $this->svc->release($tripId, $data['seat_ids'], $userId);

        return response()->json(['success'=>true, 'released'=>$result['released']]);
    }

    /** Payment success (webhook/callback) -> mark booked — JWT cho client call, 
     *  còn webhook từ cổng thanh toán thì làm endpoint riêng không yêu cầu JWT nhưng có HMAC verify.
     */
    public function paymentSuccess(Request $request, int $tripId)
    {
        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => ['integer','exists:seats,id'],
        ]);

        $userId = $request->user()->id;

        // TODO: nếu đây là callback từ client sau khi thanh toán: đã có JWT nên ok
        // Nếu là webhook từ PayOS/VNPAY: tạo controller/route riêng, xác minh chữ ký HMAC, không dùng JWT.
        $result = $this->svc->markBooked($tripId, $data['seat_ids'], $userId);

        return response()->json([
            'success' => count($result['booked']) > 0 && empty($result['failed']),
            'booked'  => $result['booked'],
            'failed'  => $result['failed'],
            'message' => $result['failed']
                ? 'Một số ghế đã được đặt trước đó'
                : 'Đặt ghế thành công',
        ]);
    }
}
