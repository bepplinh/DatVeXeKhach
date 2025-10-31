<?php

namespace App\Services\Checkout;

use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\BookingItem;
use Illuminate\Support\Str;
use App\Models\DraftCheckout;
use App\Models\TripSeatStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function finalizeFromDraft(DraftCheckout $draft, array $meta = []): Booking
    {
        $items = $draft->items()->get(['seat_id', 'price', 'seat_label']);
        if ($items->isEmpty()) {
            throw new \RuntimeException('Draft không có item.');
        }

        $data = [
            'code' => $this->generateBookingCode(),
            'trip_id' => $draft->trip_id,
            'user_id' => $draft->user_id,
            'coupon_id' => $draft->coupon_id,
            'total_price' => (int) $draft->total_price,
            'discount_amount' => (int) $draft->discount_amount,
            'status' => 'paid',
            'origin_location_id' => $draft->pickup_location_id ?? 1, // Default location
            'destination_location_id' => $draft->dropoff_location_id ?? 2, // Default location
            'pickup_address' => $draft->pickup_address ?? null,
            'dropoff_address' => $draft->dropoff_address ?? null,
            'paid_at' => now(),
        ];

        DB::beginTransaction();
        try {
            Log::info('Creating booking with data:', $data);
            $booking = Booking::create($data);

            $bookingItemsData = $items->map(function ($item) {
                return [
                    'seat_id' => (int) $item->seat_id,
                    'price' => (int) $item->price,
                    'seat_label' => $item->seat_label ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();
            $booking->items()->createMany($bookingItemsData);

            DB::commit();

            return $booking;
        } catch (\Exception $e) {
            DB::rollBack(); // Hủy bỏ mọi thay đổi nếu có lỗi xảy ra
            // Log lỗi để debug
            Log::error('BookingService::finalizeFromDraft failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Không thể hoàn tất đơn đặt vé: ' . $e->getMessage());
        }
    }

    private function generateBookingCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Booking::where('code', $code)->exists()); // Lặp lại nếu mã đã tồn tại

        return $code;
    }

    public function createFromDraft(DraftCheckout $draft)
    {
        // 0) Chuẩn bị dữ liệu / idempotency guard
        if (!$draft->relationLoaded('items') || !$draft->relationLoaded('legs')) {
            $draft->load(['items', 'legs']);
        }

        if ($draft->booking_id) {
            $existing = Booking::with(['legs.items'])->findOrFail($draft->booking_id);
            return $existing;
        }

        $booking = Booking::create([
            'user_id'           => $draft->user_id,
            'code'              => $this->generateBookingCode(),
            'status'            => 'paid',
            'payment_provider'  => $draft->payment_provider,
            'payment_intent_id' => $draft->payment_intent_id,
            'passenger_name'    => $draft->passenger_name,
            'passenger_phone'   => $draft->passenger_phone,
            'passenger_email'   => $draft->passenger_email,
            'currency'          => $draft->currency ?? 'VND',
            'total_price'       => $draft->total_price,
            'subtotal_price'    => $draft->subtotal_price,
            'discount_amount'   => $draft->discount_amount,
            'coupon_id'         => $draft->coupon_id,
            'completed_at'      => now(),
        ]);

        // Map draft_leg_id → booking_leg_id
        $legIdMap = [];

        // 2️⃣ Copy các leg sang booking_legs
        $mapByDraftLegId = [];
        $mapByTripId     = [];
        foreach ($draft->legs as $leg) {
            $bookingLeg = BookingLeg::create([
                'booking_id'            => $booking->id,
                'leg_type'              => $leg->leg,
                'trip_id'               => $leg->trip_id,
                'pickup_location_id'    => $leg->pickup_location_id,
                'dropoff_location_id'   => $leg->dropoff_location_id,
                'pickup_address'        => $leg->pickup_address,
                'dropoff_address'       => $leg->dropoff_address,
                'total_price'           => $leg->total_price,
            ]);


            // map theo draft_leg_id và theo trip_id (fallback)
            $mapByDraftLegId[$leg->id]   = $bookingLeg->id;
            $mapByTripId[$leg->trip_id]  = $bookingLeg->id;
        }

        // 3) Tạo booking_items: phải chọn đúng booking_leg_id cho từng item
        foreach ($draft->items as $item) {
            // ưu tiên dùng draft_checkout_leg_id nếu có
            $bookingLegId = null;

            if (!empty($item->draft_checkout_leg_id) && isset($mapByDraftLegId[$item->draft_checkout_leg_id])) {
                $bookingLegId = $mapByDraftLegId[$item->draft_checkout_leg_id];
            } elseif (!empty($item->trip_id) && isset($mapByTripId[$item->trip_id])) {
                // fallback theo trip_id
                $bookingLegId = $mapByTripId[$item->trip_id];
            }

            if (!$bookingLegId) {
                throw new \RuntimeException(
                    "Không tìm thấy booking_leg cho item seat {$item->seat_id} (trip {$item->trip_id})."
                );
            }

            BookingItem::create([
                // nếu bảng booking_items có cả booking_id thì thêm vào:
                // 'booking_id'     => $booking->id,
                'booking_leg_id' => $bookingLegId,
                'seat_id'        => $item->seat_id,
                'seat_label'     => $item->seat_label,
                'price'          => $item->price ?? 0,
            ]);
        }

        // 4️⃣ Load lại toàn bộ quan hệ
        $booking->load(['legs.items']);

        return $booking;
    }

    public function markBooked(int $tripId, array $seatIds, Booking $booking)
    {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        if (empty($seatIds)) return 0;

        $now  = now();
        $rows = [];

        foreach ($seatIds as $seatId) {
            $rows[] = [
                'trip_id'            => $tripId,
                'seat_id'            => $seatId,
                'booking_id'         => $booking->id,
                'is_booked'          => true,
                'booked_by_user_id'  => (int) $booking->user_id,
                'booked_at'          => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        // Upsert theo unique (trip_id, seat_id)
        // - Nếu bản ghi chưa có → insert
        // - Nếu đã có → cập nhật sang trạng thái booked + gắn booking_id
        TripSeatStatus::upsert(
            $rows,
            ['trip_id', 'seat_id'],
            ['booking_id', 'is_booked', 'booked_by_user_id', 'booked_at', 'updated_at']
        );

        return count($rows);
    }
}
