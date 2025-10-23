<?php

namespace App\Http\Controllers;

use App\Models\Seat;
use App\Models\Trip;
use App\Services\DraftCheckoutService\DraftCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\SeatFlowService;

class SeatFlowController extends Controller
{
    public function __construct(
        private SeatFlowService $svc,
        private DraftCheckoutService $drafts
    ) {}

    /** Xem trạng thái sơ đồ ghế (có thể để public nếu muốn) */
    public function index(Request $request, int $tripId)
    {
        $trip = Trip::find($tripId, ['id', 'bus_id']);
        if (!$trip) {
            return response()->json(['success'=>false, 'message'=>'Trip not found'], 404);
        }

        // TODO: lấy danh sách ID ghế của bus này
        $allSeatIds = Seat::where('bus_id', $trip->bus_id)->pluck('id')->all();

        return response()->json([
            'success' => true,
            'status'  => $this->svc->loadStatus($tripId, $allSeatIds),
        ]);
    }

    /** Soft-select (tooltip) — yêu cầu login để tránh spam */
    public function select(Request $request, int $tripId)
    {
        $trip = Trip::find($tripId, ['id', 'bus_id']);
        if (!$trip) {
            return response()->json(['success'=>false, 'message'=>'Trip not found'], 404);
        }

        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('seats','id')->where(fn($q) => $q->where('bus_id', $trip->bus_id)),
            ],
            'hint_ttl'   => ['nullable','integer','min:5','max:180'],
        ]);

        $userId  = (int) $request->user()->id;
        $hintTtl = $data['hint_ttl'] ?? 30;

        $this->svc->select($tripId, $data['seat_ids'], $userId, $hintTtl);

        return response()->json(['success'=>true, 'message'=>'Selected broadcasted']);
    }

    /** Bỏ soft-select (tooltip) — yêu cầu login */
    public function unselect(Request $request, int $tripId)
    {
        $trip = Trip::find($tripId, ['id', 'bus_id']);
        if (!$trip) {
            return response()->json(['success'=>false, 'message'=>'Trip not found'], 404);
        }

        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('seats','id')->where(fn($q) => $q->where('bus_id', $trip->bus_id)),
            ],
        ]);

        $userId = (int) $request->user()->id;

        $this->svc->unselect($tripId, $data['seat_ids'], $userId);

        return response()->json(['success'=>true, 'message'=>'Unselected broadcasted']);
    }

    /** Checkout: LOCK ghế bằng Redis (yêu cầu login + X-Session-Token) */
    public function checkout(Request $request, int $tripId)
    {
        $trip = Trip::find($tripId, ['id', 'bus_id']);
        if (!$trip) {
            return response()->json(['success'=>false, 'message'=>'Trip not found'], 404);
        }

        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('seats','id')->where(fn($q) => $q->where('bus_id', $trip->bus_id)),
            ],
            'ttl'        => ['nullable','integer','min:10','max:600'],
            'from_location_id' => ['nullable','integer','exists:locations,id'],
            'to_location_id'   => ['nullable','integer','exists:locations,id'],
        ]);

        $userId = (int) $request->user()->id;
        $token  = $request->header('X-Session-Token') ?? (string)$userId; 

        $result = $this->svc->checkout(
            $tripId,
            $data['seat_ids'],
            $token,
            $data['ttl'] ?? 180,
            6,
            $userId,
            $data['from_location_id'] ?? null,
            $data['to_location_id'] ?? null
        );

        // Map ID -> số ghế để hiển thị đẹp
        $allIds  = array_values(array_unique(array_merge($result['failed'] ?? [], $result['locked'] ?? [])));
        $seatMap = empty($allIds)
            ? collect()
            : Seat::whereIn('id', $allIds)->pluck('seat_number', 'id');

        if (!empty($result['failed'])) {
            $failedNumbers = collect($result['failed'])->map(fn($id) => $seatMap[$id] ?? (string)$id)->all();
            $list = implode(', ', $failedNumbers);

            return response()->json([
                'success'    => false,
                'locked'     => [],
                'failed'     => $failedNumbers,
                'failed_ids' => $result['failed'],
                'message'    => "Ghế {$list} đang được người khác giữ/đặt. Vui lòng chọn ghế khác.",
            ], 409);
        }

        $ttl = (int) ($data['ttl'] ?? 180);
        $draft = $this->drafts->startFromLockedSeats(
            tripId:       $tripId,
            seatIds:      $result['locked'],
            sessionToken: $token,
            userId:       $userId ?: null,
            ttlSeconds:   $ttl,
            fromLocationId: $data['from_location_id'] ?? null,
            toLocationId: $data['to_location_id'] ?? null
        );

        $lockedNumbers = collect($result['locked'])->map(fn($id) => $seatMap[$id] ?? (string)$id)->all();

        return response()->json([
            'success'         => true,
            'locked'          => $lockedNumbers,          // ví dụ ["A1","A2"]
            'locked_ids'      => $result['locked'],       // id nguyên bản
            'failed'          => [],
            'lock_expires_at' => $result['lock_expires_at'],
            'message'         => 'Đã giữ chỗ. Vui lòng điền thông tin & thanh toán.',
        ]);
    }

    /** Release lock: bỏ giữ chỗ (yêu cầu login + X-Session-Token) */
    public function release(Request $request, int $tripId)
    {
        $trip = Trip::find($tripId, ['id', 'bus_id']);
        if (!$trip) {
            return response()->json(['success'=>false, 'message'=>'Trip not found'], 404);
        }

        $data = $request->validate([
            'seat_ids'   => ['required','array','min:1'],
            'seat_ids.*' => [
                'integer',
                Rule::exists('seats','id')->where(fn($q) => $q->where('bus_id', $trip->bus_id)),
            ],
        ]);

        $userId = (int) $request->user()->id;
        $token  = $request->header('X-Session-Token') ?? (string)$userId;

        $result = $this->svc->release($tripId, $data['seat_ids'], $token, $userId);

        return response()->json([
            'success'  => true,
            'released' => $result['released'],
            'failed'   => $result['failed'],
        ]);
    }

    /**
     * Confirm (sau thanh toán thành công) -> BOOKED (ghi DB)
     * Client gọi với JWT + idempotency_key để chống double-submit.
     * Nếu có webhook từ cổng thanh toán, hãy làm route/controller riêng để verify HMAC.
     */
    public function confirm(Request $request)
    {
        $data = $request->validate([
            'trip_id'         => ['required','integer','exists:trips,id'],
            'seat_ids'        => ['required','array','min:1'],
            'seat_ids.*'      => ['integer','exists:seats,id'],
            'idempotency_key' => ['required','string','max:64'],
        ]);

        // Optionally validate seat thuộc đúng bus của trip:
        $busId = Trip::where('id', $data['trip_id'])->value('bus_id');
        $count = Seat::whereIn('id', $data['seat_ids'])->where('bus_id', $busId)->count();
        if ($count !== count($data['seat_ids'])) {
            return response()->json(['success'=>false, 'message'=>'Seat(s) not belong to trip bus'], 422);
        }

        $userId = (int) $request->user()->id;
        $token  = $request->header('X-Session-Token') ?? (string)$userId;

        $res = $this->svc->markBooked(
            $data['trip_id'],
            $data['seat_ids'],
            $token,
            $userId,
            $data['idempotency_key']
        );

        if (!empty($res['failed'])) {
            return response()->json([
                'success' => false,
                'booked'  => $res['booked'] ?? [],
                'failed'  => $res['failed'],
                'message' => 'Một số ghế đã được đặt trước đó.',
            ], 409);
        }

        return response()->json([
            'success'    => true,
            'booking_id' => $res['booking_id'] ?? null,
            'booked'     => $res['booked'] ?? [],
            'message'    => 'Đặt ghế thành công.',
        ]);
    }
}