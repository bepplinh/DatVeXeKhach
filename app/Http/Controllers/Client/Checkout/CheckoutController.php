<?php

namespace App\Http\Controllers\Client\Checkout;

use App\Events\SeatBooked;
use App\Services\SeatFlowService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\Checkout\PayOSService;
use App\Services\Checkout\BookingService;
use App\Http\Requests\Draft\UpdateDraftInfoRequest;
use App\Services\DraftCheckoutService\DraftCheckoutService;

class CheckoutController extends Controller
{
    public function __construct(
        private DraftCheckoutService $drafts,
        private SeatFlowService $seatFlow,
        private BookingService $bookings,
        private PayOSService $payos
    ) {}

    public function updateDraftInfoById(UpdateDraftInfoRequest $request, int $tripId, int $draftId)
    {
        $sessionToken = $request->header('X-Session-Token');
        $data = $request->validated();
        $userId = Auth::id() ?? null;

        $updated = $this->drafts->updateDraftBySession(
            draftId: $draftId,
            sessionToken: $sessionToken,
            payload: $data + ['payment_provider' => $data['payment_provider']]
        );

        $booking = null;

        if ($data['payment_provider'] === 'cash') {
            $seatIds = $updated->items->pluck('seat_id')->all();

            $booking = DB::transaction(function () use ($updated, $tripId, $userId, $seatIds, $sessionToken) {
                $this->seatFlow->assertSeatsLockedByToken(
                    tripId: $tripId,
                    seatIds: $seatIds,
                    token: $sessionToken
                );

                $booking = $this->bookings->finalizeFromDraft($updated);

                $updated->update([
                    'status'            => 'paid',
                    'payment_provider'  => 'cash',
                    'payment_intent_id' => null,
                    'completed_at'      => now(),
                    'booking_id'        => $booking->id,
                ]);

                return $booking; 
            });

            // 2) Side-effects sau commit (ngoài transaction)
            $this->seatFlow->markSeatsAsBooked(
                tripId: $tripId,
                seatIds: $seatIds,
                userId: $userId
                );

            $this->seatFlow->releaseLocksAfterBooked(tripId: $tripId, seatIds: $seatIds);

            broadcast(new SeatBooked(
                tripId: $tripId,
                seatIds: $seatIds,
                bookingId: $booking->id,
                userId: $booking->user_id,
                status: 'booked'
            ))->toOthers();

            return response()->json([
                'status'  => 'success',
                'message' => 'Booking completed successfully.'
            ], 200);
        }

        if ($data['payment_provider'] === 'payos') {
            $seatIds = $updated->items->pluck('seat_id')->all();
        
            // A. Lock phải thuộc phiên này
            $this->seatFlow->assertSeatsLockedByToken(
                tripId: $tripId,
                seatIds: $seatIds,
                token:  $sessionToken
            );
        
            // B. Gia hạn lock cho cửa sổ thanh toán (vd 15 phút)
            $ttl = 15 * 60;
            $this->seatFlow->renewLocksForPayment($updated, $ttl);
        
            // C. Gọi PayOS tạo link (HTTP call => làm ngoài transaction)
            $payment = $this->payos->createLinkFromDraft($updated);
        
            // D. Lưu trạng thái draft (transaction ngắn)
            DB::transaction(function () use ($updated, $payment, $ttl) {
                // idempotent: nếu đã có orderCode & status=paying thì có thể skip update
                $updated->update([
                    'payment_provider'   => 'payos',
                    'payment_intent_id'  => $payment->orderCode,
                    'status'             => 'paying',
                    'payment_expires_at' => now()->addSeconds($ttl), // nếu có cột
                ]);
            });
        
            return response()->json([
                'success'     => true,
                'status'      => 'pending',
                'payment_url' => $payment->checkoutUrl,
                'orderCode'   => $payment->orderCode,
                'expires_in'  => $ttl,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Hình thức thanh toán không hợp lệ.',
        ], 400);
    }
}
