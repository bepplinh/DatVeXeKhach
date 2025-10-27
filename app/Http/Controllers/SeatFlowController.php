<?php

namespace App\Http\Controllers;

use App\Models\Seat;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\SeatFlowService;
use App\Services\DraftCheckoutService\DraftCheckoutService;

class SeatFlowController extends Controller
{
    public function __construct(
        private SeatFlowService $svc,
        private DraftCheckoutService $drafts
    ) {}

    /** Xem trạng thái sơ đồ ghế (theo 1 trip) */
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
            'status'  => $this->svc->loadStatus($tripId, $allSeatIds), // giữ nguyên hàm loadStatus cũ nếu bạn có
        ]);
    }

    /** Soft-select (tooltip) — yêu cầu login để tránh spam (giữ nguyên per-trip) */
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

    /** Bỏ soft-select (tooltip) — yêu cầu login (giữ nguyên per-trip) */
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

    /**
     * NEW: Checkout multi-trip — LOCK ghế cho nhiều trip trong 1 lần (atomic).
     * Không còn param {tripId} trên route này nữa.
     *
     * Body:
     * {
     *   "token": "sess_abc123",
     *   "ttl_seconds": 180,
     *   "trips": [
     *     { "trip_id": 101, "seat_ids": [12,13], "leg": "OUT" },
     *     { "trip_id": 202, "seat_ids": [5],     "leg": "IN"  }
     *   ]
     * }
     */
    public function checkoutMulti(Request $request)
    {
        $data = $request->validate([
            'token'       => ['nullable','string','max:128'],
            'ttl_seconds' => ['nullable','integer','min:10','max:1200'],
            'trips'       => ['required','array','min:1'],
            'trips.*.trip_id'  => ['required','integer','exists:trips,id'],
            'trips.*.seat_ids' => ['required','array','min:1'],
            'trips.*.seat_ids.*' => ['required','integer','exists:seats,id'],
            'trips.*.leg'       => ['nullable','string','max:10'], // OUT/IN nếu bạn dùng
        ]);

        // (Tuỳ chọn) ràng buộc seat thuộc đúng bus của trip tương ứng
        // Bạn có thể bỏ nếu đã đảm bảo ở service.
        foreach ($data['trips'] as $t) {
            $busId = Trip::where('id', $t['trip_id'])->value('bus_id');
            $cnt   = Seat::whereIn('id', $t['seat_ids'])->where('bus_id', $busId)->count();
            if ($cnt !== count($t['seat_ids'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Một số seat không thuộc bus của trip tương ứng.',
                    'meta'    => ['trip_id'=>$t['trip_id']]
                ], 422);
            }
        }

        $userId = (int) optional($request->user())->id;
        $token  = (string)($data['token'] ?? ($request->header('X-Session-Token') ?? (string)$userId));
        $ttl    = (int)($data['ttl_seconds'] ?? \App\Services\SeatFlowService::DEFAULT_TTL);

        $res = $this->svc->checkout(
            trips: $data['trips'],
            token: $token,
            ttlSeconds: $ttl,
            maxPerSessionPerTrip: \App\Services\SeatFlowService::MAX_PER_SESSION_PER_TRIP,
            userId: $userId ?: null
        );

        return response()->json($res, $res['success'] ? 200 : 409);
    }

    /**
     * NEW: Release lock theo token + danh sách trip (không release lẻ từng ghế).
     *
     * Body:
     * {
     *   "token": "sess_abc123",
     *   "trip_ids": [101, 202]
     * }
     */
    public function releaseByToken(Request $request)
    {
        $data = $request->validate([
            'token'    => ['nullable','string','max:128'],
            'trip_ids' => ['required','array','min:1'],
            'trip_ids.*' => ['required','integer','exists:trips,id'],
        ]);

        $userId = (int) optional($request->user())->id;
        $token  = (string)($data['token'] ?? ($request->header('X-Session-Token') ?? (string)$userId));

        $released = $this->svc->releaseByToken($data['trip_ids'], $token);

        return response()->json([
            'success'  => true,
            'released' => $released
        ]);
    }

    /**
     * NEW: Confirm theo draft (khuyến nghị).
     * Body:
     * {
     *   "draft_id": 123,
     *   "idempotency_key": "confirm-abc-001"
     * }
     *
     * Gợi ý luồng:
     * - Tải draft + items -> build $tripSeatMap [tripId => [seatIds...]]
     * - $this->svc->assertMultiLockedByToken($tripSeatMap, $draft->session_token)
     * - DB::transaction finalize tạo nhiều Booking từ draft (hoặc 1 Booking gộp tuỳ nghiệp vụ)
     */
    public function confirm(Request $request)
    {
        $data = $request->validate([
            'draft_id'        => ['required','integer','exists:draft_checkouts,id'],
            'idempotency_key' => ['required','string','max:64'],
        ]);

        $draft = $this->drafts->findWithItems($data['draft_id']); // bạn cần có hàm hỗ trợ (hoặc DraftCheckout::with('items')->find())
        if (!$draft) {
            return response()->json(['success'=>false,'message'=>'Draft not found'], 404);
        }

        // Build map trip => seatIds từ draft
        $tripSeatMap = [];
        foreach ($draft->items as $it) {
            $tripSeatMap[(int)$it->trip_id][] = (int)$it->seat_id;
        }

        // Xác nhận còn lock trước khi finalize
        try {
            $this->svc->assertMultiLockedByToken($tripSeatMap, $draft->session_token);
        } catch (\Throwable $e) {
            return response()->json([
                'success'=>false,
                'message'=>'Lock đã hết hạn hoặc không còn thuộc về phiên này.',
                'error'  => $e->getMessage(),
            ], 409);
        }

        // TODO: finalize booking từ draft (gọi BookingService / DraftCheckoutService)
        // ví dụ:
        // $booking = app(\App\Services\Checkout\BookingService::class)
        //     ->finalizeFromDraft($draft, $data['idempotency_key']);

        // Ở đây tạm trả về thành công giả lập:
        return response()->json([
            'success'    => true,
            'booking_id' => null, // điền id thực tế nếu đã finalize
            'message'    => 'Đã xác nhận thanh toán & sẵn sàng finalize booking.',
        ]);
    }
}
