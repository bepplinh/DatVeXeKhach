<?php

namespace App\Services\DraftCheckoutService;

use App\Models\DraftCheckout;
use App\Services\Coupon\CalcCoupon;
use Illuminate\Support\Facades\DB;

class UpdateDraftPayment
{
    public function __construct(
        private CalcCoupon $calcCoupon
    ) {}
    public function updateDraftCheckout(
        int $draftId,
        string $sessionToken,
        array $payload
    ) {
        return DB::transaction(function () use ($draftId, $sessionToken, $payload) {
            $draft = DraftCheckout::with(['legs'])
                ->whereKey($draftId)
                ->where('session_token', $sessionToken)
                ->whereIn('status', ['pending', 'paying'])
                ->lockForUpdate()
                ->firstOrFail();

            $assignables = [
                'passenger_name',
                'passenger_phone',
                'passenger_email',
                'coupon_id',
                'payment_provider',
                'payment_intent_id',
                'pickup_location_id',
                'dropoff_location_id',
                'pickup_address',
                'dropoff_address',
            ];

            $updateData = array_intersect_key($payload, array_flip($assignables));
            if (!empty($updateData)) {
                $draft->fill($updateData);
            }

             // Nếu có tổng giá trị (total_price) cập nhật lại
             if (isset($payload['total_price'])) {
                $draft->total_price = (int) $payload['total_price'];
            }

            $draft->save();

              /*
            |--------------------------------------------------------------------------
            | 2️⃣ Cập nhật thông tin pickup/dropoff của từng leg (OUT / RETURN)
            |--------------------------------------------------------------------------
            */
            if (isset($payload['legs']) && is_array($payload['legs'])) {
                foreach ($payload['legs'] as $legData) {
                    $legType = strtoupper($legData['leg_type'] ?? 'OUT'); // OUT hoặc RETURN
                    $leg = $draft->legs->firstWhere('leg', $legType);

                    if ($leg) {
                        $legAssignables = [
                            'pickup_location_id', 'dropoff_location_id',
                            'pickup_address', 'dropoff_address',
                        ];

                        $legUpdate = array_intersect_key($legData, array_flip($legAssignables));

                        // Nếu là chiều RETURN thì đảo pickup/dropoff so với chiều OUT nếu người dùng chọn khứ hồi
                        if (($legType === 'RETURN') && ($payload['is_round_trip'] ?? false)) {
                            $tempPickup = $legUpdate['pickup_location_id'] ?? null;
                            $legUpdate['pickup_location_id'] = $legUpdate['dropoff_location_id'] ?? $tempPickup;
                            $legUpdate['dropoff_location_id'] = $tempPickup;
                        }

                        if (!empty($legUpdate)) {
                            $leg->fill($legUpdate);
                            $leg->save();
                        }
                    }
                }
            }

            if (isset($payload['coupon_id'])) {
                $res = $this->calcCoupon->applyCoupon(
                    couponId: (int)$payload['coupon_id'],
                    draft: $draft,
                    userId: $draft->user_id
                );

                $coupon = $res['coupon'];
                $discountAmount = (int) $res['discount_amount'];

                $subtotal = (int) $draft->items()->sum('price');
                $totalAfter = max(0, $subtotal - $discountAmount);

                $draft->update([
                    'coupon_id'      => $coupon->id,
                    'discount_amount'=> $discountAmount,
                    'total_price'    => $totalAfter,
                ]);
            }

            return $draft->fresh(['legs']);
        });
    }
}
