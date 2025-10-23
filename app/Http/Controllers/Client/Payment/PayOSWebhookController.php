<?php
// app/Http/Controllers/Client/Payment/PayOSWebhookController.php
namespace App\Http\Controllers\Client\Payment;

use App\Events\SeatBooked;
use Illuminate\Http\Request;
use App\Models\DraftCheckout;
use App\Services\SeatFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Checkout\PayOSService;
use App\Services\Checkout\BookingService;
use Illuminate\Support\Facades\Log;

class PayOSWebhookController extends Controller
{
    public function __construct(
        private PayOSService   $payos,
        private SeatFlowService $seats,
        private BookingService  $booking,
    ) {}

    public function handle(Request $req): JsonResponse
    {
        // Log thô để debug khi cần
        Log::info('PayOS webhook: RAW', [
            'headers' => $req->headers->all(),
            'json'    => $req->json()->all(),
        ]);

        $payload = $req->json()->all();

        // 1) Verify chữ ký (bật trên non-local)
        if (config('app.env') !== 'local') {
            [$ok, $data] = $this->payos->verifyWebhook($payload);
            if (!$ok) {
                Log::warning('PayOS webhook: invalid signature');
                // Trả 400 để PayOS retry (tuỳ bạn muốn)
                return response()->json(['ok' => false, 'err' => 'invalid_signature'], 400);
            }
        } else {
            // Khi local, tin vào data từ payload để test
            $data = $payload['data'] ?? [];
        }

        // 2) Chỉ xử lý khi đã PAID (điểm mấu chốt cần sửa)
        $success = (bool)($payload['success'] ?? false);
        $code = (string)($data['code'] ?? '');
        
        if (!$success || $code !== '00') {
            Log::info('PayOS webhook: ignored non-success code', [
                'success' => $success,
                'code' => $code,
                'data' => $data,
            ]);
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        // 3) Lấy orderCode để map về draft
        $orderCode = $data['orderCode'] ?? null;
        if (!$orderCode) {
            Log::warning('PayOS webhook: missing orderCode');
            // Thiếu định danh → trả 200 nhưng báo lỗi nội bộ để không bị retry vô ích
            return response()->json(['ok' => true, 'err' => 'missing_orderCode']);
        }

        // Log tìm draft để soi lỗi so khớp kiểu
        Log::info('PayOS webhook orderCode lookup', [
            'orderCode' => $orderCode,
            'type'      => gettype($orderCode),
            'drafts'    => DraftCheckout::query()
                ->where('payment_provider', 'payos')
                ->where(function ($q) use ($orderCode) {
                    $q->where('payment_intent_id', $orderCode)
                      ->orWhere('payment_intent_id', (string)$orderCode)
                      ->orWhere('payment_intent_id', (int)$orderCode);
                })
                ->get(['id','payment_intent_id','status'])
                ->toArray()
        ]);

        $result = DB::transaction(function () use ($orderCode, $data) {
            $draft = DraftCheckout::query()
                ->where('payment_provider', 'payos')
                ->where(function($q) use ($orderCode) {
                    $q->where('payment_intent_id', $orderCode)
                      ->orWhere('payment_intent_id', (string)$orderCode)
                      ->orWhere('payment_intent_id', (int)$orderCode);
                })
                ->lockForUpdate()
                ->first();

            if (!$draft) {
                Log::warning('PayOS webhook: draft not found', ['orderCode' => $orderCode]);
                // Không ném lỗi 4xx để tránh retry vô hạn
                return ['draftId' => null, 'bookingId' => null, 'seatIds' => []];
            }

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
                    'draft_id' => $draft->id, 'status' => $draft->status
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
                    tripId:    $tripId,
                    seatIds:   $seatIds,
                    bookingId: $booking->id,
                    userId:    (int)$booking->user_id,
                    status:    'booked'
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
