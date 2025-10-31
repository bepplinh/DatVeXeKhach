<?php

namespace App\Http\Controllers\Client\Checkout;

use Throwable;
use App\Models\DraftCheckout;
use App\Services\SeatFlowService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Checkout\BookingService;
use App\Http\Requests\Draft\UpdateDraftRequest;
use App\Services\Checkout\PayOSService;
use App\Services\SeatFlow\ReleaseLocksAfterBooked;
use App\Services\DraftCheckoutService\UpdateDraftPayment;

class CheckoutController extends Controller
{
    public function __construct(
        private UpdateDraftPayment $updateDraftPayment,
        private SeatFlowService $seatFlow,
        private PayOSService $payOS,
        private BookingService $bookingService,
        private ReleaseLocksAfterBooked $releaseLocksAfterBooked
    ) {}

    public function updateDraftPayment(
        UpdateDraftRequest $request,
        int $draftId
    ) {
        $sessionToken = $request->header('X-Session-Token');
        $data = $request->validated();

        $updated = $this->updateDraftPayment->updateDraftCheckout(
            draftId: $draftId,
            sessionToken: $sessionToken,
            payload: $data
        );

        try {
            $provider = strtolower((string)($data['payment_provider'] ?? $updated->payment_provider ?? ''));
            if ($provider === 'cash') {
                $booking = DB::transaction(function () use ($draftId, $sessionToken) {
                    $draft = DraftCheckout::query()
                        ->with(['legs', 'items'])
                        ->where('id', $draftId)
                        ->where('session_token', $sessionToken)
                        ->whereIn('status', ['pending', 'paying'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $seatsByTrip = [];
                    foreach ($draft->items as $it) {
                        $tid = (int) $it->trip_id;
                        $sid = (int) $it->seat_id;
                        if ($tid > 0 && $sid > 0) {
                            $seatsByTrip[$tid] = $seatsByTrip[$tid] ?? [];
                            $seatsByTrip[$tid][] = $sid;
                        }
                    }

                    // unique seatIds mỗi trip
                    foreach ($seatsByTrip as $tid => $arr) {
                        $seatsByTrip[$tid] = array_values(array_unique($arr));
                    }

                    // 2b) Khẳng định tất cả ghế đang được lock bởi sessionToken
                    foreach ($seatsByTrip as $tripId => $seatIds) {
                        $this->seatFlow->assertSeatsLockedByToken(
                            tripId: $tripId,
                            seatIds: $seatIds,
                            token: $draft->session_token
                        );
                    }

                    // 2c) Tạo booking + booking_legs + booking_items từ draft
                    $booking = $this->bookingService->createFromDraft($draft);

                    // 2d) Tạo Trip Seat Status
                    foreach ($seatsByTrip as $tripId => $seatIds) {
                        $this->bookingService->markBooked(
                            tripId: $tripId,
                            seatIds: $seatIds,
                            booking: $booking
                        );
                    }

                    // 2e) Cập nhật draft → paid, completed_at, link booking_id
                    $draft->update([
                        'status'      => 'paid',
                        'booking_id'  => $booking->id,
                        'completed_at' => now(),
                    ]);

                    // 2f) Sau khi đã ghi DB thành công → bỏ lock trong Redis (và chuyển set "booked" nếu bạn có)
                    foreach ($seatsByTrip as $tripId => $seatIds) {
                        $this->releaseLocksAfterBooked->releaseLocksAfterBooked(
                            tripId: $tripId,
                            seatIds: $seatIds,
                            token: $draft->session_token,
                            bookingId: $booking->id
                        );
                    }

                    return $booking;
                });

                // Sau DB::transaction(...) đã trả về $booking
                $booking->loadMissing(['legs.items']);

                // 1) Build payload cho SeatBooked (multi-trip)
                $bookedBlocks = []; // mảng các block theo trip
                foreach ($booking->legs as $leg) {
                    $tripId = (int) $leg->trip_id;

                    $seatIds    = [];
                    $seatLabels = [];
                    foreach ($leg->items as $it) {
                        $seatIds[]    = (int) $it->seat_id;
                        $seatLabels[] = (string) $it->seat_label;
                    }

                    $bookedBlocks[] = [
                        'trip_id'        => $tripId,
                        'seat_ids'       => $seatIds,
                        'seat_labels'    => $seatLabels,        // optional
                        'leg_type'       => $leg->leg_type ?? null, // optional
                        'booking_leg_id' => $leg->id,           // optional
                    ];
                }

                // 2) Phát SEAT_BOOKED (đa kênh: trip.{tripId} + client.session.{token})
                event(new \App\Events\SeatBooked(
                    sessionToken: $sessionToken,
                    bookingId: $booking->id,
                    booked: $bookedBlocks,
                    userId: $booking->user_id
                ));

                return response()->json([
                    'success' => true,
                    'message' => 'Đặt vé thành công.',
                ]);

                // 3) (Tuỳ chọn) Phát SEAT_UNLOCKED_AFTER_BOOKED nếu bạn muốn UI tắt trạng thái "đang lock"
                //
                // - dùng kết quả từ releaseLocksAfterBooked() ở mỗi trip
                // - ví dụ bạn đã gom lại trong $releasedMap dạng:
                //   $releasedMap = [ tripId => [seatId1, seatId2, ...], ... ]
                // if (!empty($releasedMap ?? [])) {
                //     event(new \App\Events\SeatUnlockedAfterBooked(
                //         sessionToken: $draft->session_token,
                //         released: $releasedMap,  // map trip_id => seat_ids[]
                //         reason: 'finalized_to_booked',
                //         bookingId: $booking->id
                //     ));
                // }
            }

            //payment provider === payos
            if ($provider === 'payos') {
                try {
                    $res = $this->payOS->createLinkFromDraft($updated);

                    if (is_object($res)) $res = (array) $res;

                    $paymentUrl = $res['checkoutUrl'] ?? null; // đúng theo service
                    $orderCode  = $res['orderCode']  ?? null;  // đúng theo service

                    $updated->update([
                        'status'             => 'paying',
                        'payment_provider'   => 'payos',
                        'payment_intent_id'  => (string) $orderCode,
                        'expires_at'         => now()->addMinutes(15),
                    ]);

                    return response()->json([
                        'success'     => true,
                        'payment_url' => $paymentUrl,
                        'order_code'  => $orderCode,
                        'expires_at'  => $updated->expires_at,
                    ]);
                } catch (Throwable $e) {
                    return response()->json([
                        'message' => 'Co loi khi tao link thanh toan PayOS, vui long thu lai',
                        'error'   => $e->getMessage(),
                    ], 500);
                }
            }
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Co loi khi thanh toan, vui long thu lai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
