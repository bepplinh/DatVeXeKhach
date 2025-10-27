<?php

namespace App\Services\Checkout;

use App\Models\Booking;
use Illuminate\Support\Str;
use App\Models\DraftCheckout;
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
            'code' => $this->generateUniqueCode(),
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

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Booking::where('code', $code)->exists()); // Lặp lại nếu mã đã tồn tại

        return $code;
    }
}
