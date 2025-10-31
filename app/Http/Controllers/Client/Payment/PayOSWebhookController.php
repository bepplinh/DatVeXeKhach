<?php
// app/Http/Controllers/Client/Payment/PayOSWebhookController.php
namespace App\Http\Controllers\Client\Payment;

use Throwable;
use App\Events\SeatBooked;
use Illuminate\Http\Request;
use App\Models\DraftCheckout;
use App\Services\SeatFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\Checkout\PayOSService;
use App\Services\Checkout\BookingService;
use App\Services\SeatFlow\SeatReleaseService;

class PayOSWebhookController extends Controller
{
    public function __construct(
        private PayOSService   $payos,
        private SeatFlowService $seats,
        private SeatReleaseService $seatRelease,
        private BookingService  $booking,
    ) {}

    public function handle(Request $req): JsonResponse
    {
        // 0) Log thô phục vụ debug
        Log::info('PayOS webhook: RAW', [
            'headers' => $req->headers->all(),
            'json'    => $req->json()->all(),
        ]);

        $payload = $req->json()->all();
        $data    = $payload['data'] ?? [];

        // 1) Verify chữ ký (chỉ bật ngoài local)
        if (config('app.env') !== 'local') {
            [$ok, $verified] = $this->payos->verifyWebhook($payload);
            if (!$ok) {
                Log::warning('PayOS webhook: invalid signature');
                // 400 để PayOS retry (tùy policy của bạn)
                return response()->json(['ok' => false, 'err' => 'invalid_signature'], 400);
            }
            // Một số cổng trả data sau verify; nếu có thì dùng
            if (is_array($verified)) {
                $data = $verified + $data; // không ghi đè mù quáng
            }
        }

        // 2) Xác định trạng thái PAID/SUCCESS theo nhiều biến thể payload
        $success     = (bool)($payload['success'] ?? false);
        $rootCode    = (string)($payload['code'] ?? '');
        $dataCode    = (string)($data['code'] ?? '');
        $dataStatus  = strtoupper((string)($data['status'] ?? '')); // "PAID"/"SUCCESS"/"CANCELLED"/"FAILED"/"EXPIRED"
        $isPaid      = ($success && ($rootCode === '00' || $dataCode === '00')) || in_array($dataStatus, ['PAID', 'SUCCESS'], true);

        // 3) Lấy orderCode theo các khả năng phổ biến
        $orderCode = $data['orderCode']
            ?? $payload['orderCode']
            ?? $data['order_code']
            ?? null;

        if (!$orderCode) {
            Log::warning('PayOS webhook: missing orderCode', ['payload' => $payload]);
            // Trả 200 để không retry vô hạn, nhưng báo err để bạn soi log
            return response()->json(['ok' => true, 'err' => 'missing_orderCode']);
        }

        // 4) Nếu KHÔNG phải PAID/SUCCESS → cập nhật trạng thái (optional) và/hoặc unlock
        if (!$isPaid) {
            $draft = DraftCheckout::query()
                ->where('payment_provider', 'payos')
                ->where(function ($q) use ($orderCode) {
                    $q->where('payment_intent_id', $orderCode)
                        ->orWhere('payment_intent_id', (string)$orderCode)
                        ->orWhere('payment_intent_id', (int)$orderCode);
                })
                ->first();

            if ($draft) {
                $statusMap = [
                    'CANCELLED' => 'cancelled',
                    'FAILED'    => 'failed',
                    'EXPIRED'   => 'expired',
                ];
                $newStatus = $statusMap[$dataStatus] ?? 'paying';
                if (in_array($newStatus, ['cancelled', 'failed', 'expired'], true)) {
                    $draft->update(['status' => $newStatus]);
                    try {
                        $this->seatRelease->cancelAllBySession($draft->session_token);
                    } catch (Throwable $e) {
                        return response()->json([
                            'message' => 'Error updating draft status: ' . $e->getMessage()
                        ]);
                    }
                }
            }
            return response()->json(['ok' => true, 'ignored' => true]);
        }

         // 5) ĐÃ PAID → chốt vé trong 1 transaction, idempotent
        $result = DB::transaction(function () use ($orderCode) {
            $draft = DraftCheckout::query()
                ->with(['legs.items'])
                ->where('payment_provider', 'payos')
                ->where(function ($q) use ($orderCode) {
                    $q->where('payment_intent_id', $orderCode)
                        ->orWhere('payment_intent_id', (string)$orderCode)
                        ->orWhere('payment_intent_id', (int)$orderCode);
                })->lockForUpdate()
                ->first();

            if (!$draft) {
                return response()->json([
                    'message' => 'DraftCheckout not found for orderCode: ' . $orderCode
                ], 404);
            }

            if ($draft->status === 'paid' && $draft->booking_id) {
                $bookedBlocks = [];
                foreach ($draft->legs as $leg) {
                    $bookedBlocks[] = [
                        'trip_id'        => (int)$leg->trip_id,
                        'seat_ids'       => $leg->items->pluck('seat_id')->map(fn($v)=>(int)$v)->all(),
                        'seat_labels'    => $leg->items->pluck('seat_label')->map(fn($v)=>(string)$v)->all(),
                        'leg'       => $leg->leg,
                        'booking_leg_id' => $leg->id,
                    ];
                }
    
                return [
                    'draftId'      => $draft->id,
                    'bookingId'    => $draft->booking_id,
                    'sessionToken' => $draft->session_token,
                    'userId'       => (int)($draft->user_id ?? 0),
                    'bookedBlocks' => $bookedBlocks,
                ];
            }
        });

            // Idempotent: đã trả tiền trước đó
            if ($draft->status === 'paid' && $draft->booking_id) {
                return [
                    'draftId'   => $draft->id,
                    'bookingId' => $draft->booking_id,
                    'seatIds'   => $draft->items()->pluck('seat_id')->all(),
                    'tripId'    => $draft->trip_id,
                    'userId'    => $draft->user_id,
                ];
            }

            // Chỉ chốt khi đang paying (tránh nhảy cóc trạng thái)
            if ($draft->status !== 'paying') {
                Log::info('PayOS webhook: draft not in paying state', [
                    'draft_id' => $draft->id,
                    'status' => $draft->status
                ]);
                return [
                    'draftId'   => $draft->id,
                    'bookingId' => $draft->booking_id,
                    'seatIds'   => $draft->items()->pluck('seat_id')->all(),
                    'tripId'    => $draft->trip_id,
                    'userId'    => $draft->user_id,
                ];
            }

            $tripId  = (int)$draft->trip_id;
            $seatIds = $draft->items()->pluck('seat_id')->all();
            $userId  = (int)($draft->user_id ?? 0);

            // (Khuyến nghị) Nếu bạn muốn đối chiếu số tiền/currency để log cảnh báo:
            // $paidAmount   = isset($data['amount']) ? (int)$data['amount'] : null;
            // $paidCurrency = $data['currency'] ?? null;

            // 5) Tạo booking từ draft
            $booking = $this->booking->finalizeFromDraft($draft);

            // 6) Đánh dấu ghế đã book (tất cả hoặc không gì)
            $this->seats->markSeatsAsBooked(
                tripId: $tripId,
                seatIds: $seatIds,
                userId: $userId
            );

            // 7) Cập nhật draft
            $draft->update([
                'status'       => 'paid',
                'completed_at' => now(),
                'booking_id'   => $booking->id,
            ]);

            // 8) Hậu commit: giải phóng lock + broadcast realtime
            DB::afterCommit(function () use ($tripId, $seatIds, $booking) {
                $this->seats->releaseLocksAfterBooked($tripId, $seatIds);

                broadcast(new SeatBooked(
                    tripId: $tripId,
                    seatIds: $seatIds,
                    bookingId: $booking->id,
                    userId: (int)$booking->user_id,
                    status: 'booked'
                ));
            });

            return [
                'draftId'   => $draft->id,
                'bookingId' => $booking->id,
                'seatIds'   => $seatIds,
                'tripId'    => $tripId,
                'userId'    => $userId,
            ];
        });

        return response()->json([
            'ok'         => true,
            'draft_id'   => $result['draftId'] ?? null,
            'booking_id' => $result['bookingId'] ?? null,
            'seat_ids'   => $result['seatIds'] ?? [],
        ]);
    }
}
