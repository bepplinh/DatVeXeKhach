<?php

namespace App\Services;

use App\Models\DraftCheckout;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\TripSeatStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DraftCheckoutService
{
    /**
     * Tạo draft checkout mới
     */
    public function createDraft(array $data): DraftCheckout
    {
        return DraftCheckout::create($data);
    }

    /**
     * Cập nhật draft checkout
     */
    public function updateDraft(string $checkoutToken, array $data): bool
    {
        $draft = DraftCheckout::where('checkout_token', $checkoutToken)->first();
        
        if (!$draft || $draft->isExpired()) {
            return false;
        }

        return $draft->update($data);
    }

    /**
     * Lấy draft checkout theo token
     */
    public function getDraftByToken(string $checkoutToken): ?DraftCheckout
    {
        return DraftCheckout::where('checkout_token', $checkoutToken)
                           ->where('expires_at', '>', now())
                           ->first();
    }

    /**
     * Gia hạn thời gian draft
     */
    public function extendDraft(string $checkoutToken, int $minutes = 15): bool
    {
        $draft = DraftCheckout::where('checkout_token', $checkoutToken)->first();
        
        if (!$draft || $draft->isExpired()) {
            return false;
        }

        $draft->extendExpiration($minutes);
        return true;
    }

    /**
     * Chuyển draft thành booking thực tế
     */
    public function convertToBooking(string $checkoutToken): array
    {
        $draft = $this->getDraftByToken($checkoutToken);
        
        if (!$draft) {
            return [
                'success' => false,
                'message' => 'Draft checkout không tồn tại hoặc đã hết hạn'
            ];
        }

        if ($draft->status !== 'draft') {
            return [
                'success' => false,
                'message' => 'Draft checkout đã được xử lý'
            ];
        }

        try {
            DB::beginTransaction();

            // Tạo booking
            $booking = $this->createBookingFromDraft($draft);
            
            // Tạo booking items
            $this->createBookingItemsFromDraft($draft, $booking);
            
            // Tạo tickets
            $this->createTicketsFromDraft($draft, $booking);
            
            // Cập nhật trạng thái ghế
            $this->updateSeatStatuses($draft);
            
            // Đánh dấu draft đã hoàn thành
            $draft->markAsCompleted();

            DB::commit();

            return [
                'success' => true,
                'booking' => $booking,
                'message' => 'Đặt vé thành công'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Hủy draft checkout
     */
    public function cancelDraft(string $checkoutToken): bool
    {
        $draft = DraftCheckout::where('checkout_token', $checkoutToken)->first();
        
        if (!$draft) {
            return false;
        }

        // Giải phóng lock ghế nếu có
        $this->releaseSeatLocks($draft);
        
        // Đánh dấu draft đã hết hạn
        $draft->markAsExpired();
        
        return true;
    }

    /**
     * Dọn dẹp các draft đã hết hạn
     */
    public function cleanupExpiredDrafts(): int
    {
        $expiredDrafts = DraftCheckout::expired()->get();
        
        foreach ($expiredDrafts as $draft) {
            $this->releaseSeatLocks($draft);
            $draft->markAsExpired();
        }
        
        return $expiredDrafts->count();
    }

    /**
     * Tạo booking từ draft
     */
    private function createBookingFromDraft(DraftCheckout $draft): Booking
    {
        return Booking::create([
            'code' => $this->generateBookingCode(),
            'trip_id' => $draft->trip_id,
            'seat_id' => $draft->seat_ids[0], // Lấy ghế đầu tiên làm primary
            'user_id' => $draft->user_id,
            'coupon_id' => $draft->coupon_id,
            'total_price' => $draft->total_price,
            'discount_amount' => $draft->discount_amount,
            'status' => 'paid', // Đã thanh toán thành công
            'paid_at' => now(),
        ]);
    }

    /**
     * Tạo booking items từ draft
     */
    private function createBookingItemsFromDraft(DraftCheckout $draft, Booking $booking): void
    {
        foreach ($draft->seat_ids as $seatId) {
            BookingItem::create([
                'booking_id' => $booking->id,
                'seat_id' => $seatId,
                'origin_location_id' => $draft->pickup_location_id,
                'destination_location_id' => $draft->dropoff_location_id,
                'passenger_name' => $draft->passenger_name,
                'passenger_phone' => $draft->passenger_phone,
                'passenger_email' => $draft->passenger_email,
                'notes' => $draft->notes,
            ]);
        }
    }

    /**
     * Tạo tickets từ draft
     */
    private function createTicketsFromDraft(DraftCheckout $draft, Booking $booking): void
    {
        foreach ($draft->seat_ids as $seatId) {
            Ticket::create([
                'trip_id' => $draft->trip_id,
                'user_id' => $draft->user_id,
                'seat_id' => $seatId,
                'booking_id' => $booking->id,
                'price' => $draft->total_price / count($draft->seat_ids), // Chia đều giá
            ]);
        }
    }

    /**
     * Cập nhật trạng thái ghế
     */
    private function updateSeatStatuses(DraftCheckout $draft): void
    {
        foreach ($draft->seat_ids as $seatId) {
            TripSeatStatus::where('trip_id', $draft->trip_id)
                         ->where('seat_id', $seatId)
                         ->update([
                             'is_booked' => true,
                             'booked_by' => $draft->user_id,
                             'booked_at' => now(),
                             'locked_by_user_id' => null,
                             'locked_at' => null,
                             'lock_expires_at' => null,
                         ]);
        }
    }

    /**
     * Giải phóng lock ghế
     */
    private function releaseSeatLocks(DraftCheckout $draft): void
    {
        foreach ($draft->seat_ids as $seatId) {
            TripSeatStatus::where('trip_id', $draft->trip_id)
                         ->where('seat_id', $seatId)
                         ->where('locked_by_user_id', $draft->user_id)
                         ->update([
                             'locked_by_user_id' => null,
                             'locked_at' => null,
                             'lock_expires_at' => null,
                         ]);
        }
    }

    /**
     * Tạo mã booking
     */
    private function generateBookingCode(): string
    {
        do {
            $code = 'BK' . strtoupper(Str::random(8));
        } while (Booking::where('code', $code)->exists());
        
        return $code;
    }

    /**
     * Lấy danh sách draft của user
     */
    public function getUserDrafts(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return DraftCheckout::byUser($userId)
                           ->active()
                           ->with(['trip', 'pickupLocation', 'dropoffLocation'])
                           ->orderBy('created_at', 'desc')
                           ->get();
    }

    /**
     * Lấy thống kê draft
     */
    public function getDraftStats(): array
    {
        return [
            'total_drafts' => DraftCheckout::count(),
            'active_drafts' => DraftCheckout::active()->count(),
            'expired_drafts' => DraftCheckout::expired()->count(),
            'completed_drafts' => DraftCheckout::where('status', 'completed')->count(),
        ];
    }
}

