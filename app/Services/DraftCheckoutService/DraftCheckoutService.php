<?php

namespace App\Services\DraftCheckoutService;

use App\Models\Seat;
use App\Models\Trip;
use App\Models\DraftCheckout;

use Illuminate\Support\Carbon;
use App\Models\DraftCheckoutItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class DraftCheckoutService
{
    /**
     * Tạo/ghi draft từ danh sách ghế đã lock (all-or-nothing đã OK ở Redis)
     * - Idempotent theo (trip_id, session_token) khi status còn "mở" (pending|paying)
     * - Snapshot từng ghế (label/price/fare_id)
     * - Đặt expires_at khớp TTL bên Redis
     */
    public function startFromLockedSeats(
        int $tripId,
        array $seatIds,
        string $sessionToken,
        ?int $userId,
        int $ttlSeconds,
        ?int $fromLocationId = null,
        ?int $toLocationId = null
    ): DraftCheckout {
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        if (empty($seatIds)) {
            throw new \RuntimeException('seat_ids trống.');
        }

        $now   = now();
        $until = (clone $now)->addSeconds(max(30, $ttlSeconds));

        return DB::transaction(function () use ($tripId, $seatIds, $sessionToken, $userId, $now, $until, $fromLocationId, $toLocationId) {
            // 1) Tính giá & snapshot label (tuỳ hệ thống vé — thay bằng service thật nếu có)
            $seats   = Seat::whereIn('id', $seatIds)->get(['id','seat_number']);
            $pricing = $this->computeSeatSnapshots($tripId, $seats); // [seat_id => ['price'=>..,'label'=>..,'fare_id'=>..]]
            $total   = array_sum(array_map(fn($x) => (int) $x['price'], $pricing));

            // 2) Tìm draft mở (pending|paying) theo session_token + trip
            $draft = DraftCheckout::where('trip_id', $tripId)
                ->where('session_token', $sessionToken)
                ->whereIn('status', ['pending','paying'])   // đổi theo enum của bạn nếu khác
                ->lockForUpdate()
                ->first();

            if (!$draft) {
                $draft = new DraftCheckout();
                $draft->trip_id       = $tripId;
                $draft->session_token = $sessionToken;
                $draft->status        = 'pending';          // hoặc 'draft' tuỳ enum của bạn
                $draft->user_id       = $userId;
                $draft->pickup_location_id = $fromLocationId;
                $draft->dropoff_location_id = $toLocationId;
                // các field nhập sau (passenger_*, pickup_*) để null, sẽ PATCH sau
            } else {
                $draft->user_id ??= $userId;
                // Cập nhật location IDs nếu chưa có
                $draft->pickup_location_id ??= $fromLocationId;
                $draft->dropoff_location_id ??= $toLocationId;
            }

            // Đồng bộ tổng & hạn
            $draft->expires_at      = $until;
            $draft->total_price     = $total;
            $draft->discount_amount = $draft->discount_amount ?? 0;
            $draft->save();

            // 3) Đồng bộ items: xoá ghế thừa, upsert ghế hiện có
            $existing = $draft->items()->pluck('seat_id')->all();
            $toDelete = array_diff($existing, $seatIds);
            if (!empty($toDelete)) {
                DraftCheckoutItem::where('draft_checkout_id', $draft->id)
                    ->whereIn('seat_id', $toDelete)->delete();
            }

            foreach ($seatIds as $sid) {
                $snap = $pricing[$sid] ?? null;
                if (!$snap) {
                    throw new \RuntimeException("Thiếu snapshot cho seat {$sid}");
                }
                DraftCheckoutItem::updateOrCreate(
                    ['draft_checkout_id' => $draft->id, 'seat_id' => $sid],
                    [
                        'price'      => (int)$snap['price'],
                        'seat_label' => $snap['label'],
                        'fare_id'    => $snap['fare_id'] ?? null,
                    ]
                );
            }

            return $draft->fresh(['items']);
        });
    }

    /**
     * PATCH draft: cập nhật passenger/pickup/dropoff/coupon...
     * (Gọi ở bước sau, không dùng ngay sau lock.)
     */
    public function updateDraft(int|string $draftId, array $payload): DraftCheckout
    {
        return DB::transaction(function () use ($draftId, $payload) {
            /** @var DraftCheckout $draft */
            $draft = DraftCheckout::whereKey($draftId)
                ->whereIn('status', ['pending','paying'])
                ->lockForUpdate()
                ->firstOrFail();

            $assignables = [
                'passenger_name','passenger_phone','passenger_email',
                'pickup_location_id','dropoff_location_id',
                'pickup_address','dropoff_address',
                'pickup_snapshot','dropoff_snapshot',
                'booker_name','booker_phone',
                'coupon_id','notes'
            ];
            foreach ($assignables as $f) {
                if (array_key_exists($f, $payload)) $draft->{$f} = $payload[$f];
            }
            if (isset($payload['passenger_info']) && is_array($payload['passenger_info'])) {
                $draft->passenger_info = array_merge($draft->passenger_info ?? [], $payload['passenger_info']);
            }
            if (array_key_exists('discount_amount', $payload)) {
                $draft->discount_amount = (int)$payload['discount_amount'];
            }

            $draft->save();
            return $draft->fresh(['items']);
        });
    }

    /**
     * Cập nhật draft theo session token và draft ID
     * Được sử dụng trong CheckoutController để cập nhật thông tin thanh toán
     */
    public function updateDraftBySession(int|string $draftId, string $sessionToken, array $payload): DraftCheckout
    {
        return DB::transaction(function () use ($draftId, $sessionToken, $payload) {
            /** @var DraftCheckout $draft */
            $draft = DraftCheckout::whereKey($draftId)
                ->where('session_token', $sessionToken)
                ->whereIn('status', ['pending','paying'])
                ->lockForUpdate()
                ->firstOrFail();

            // Các field có thể cập nhật từ payload
            $assignables = [
                'passenger_name','passenger_phone','passenger_email',
                'pickup_location_id','dropoff_location_id',
                'pickup_address','dropoff_address',
                'pickup_snapshot','dropoff_snapshot',
                'booker_name','booker_phone',
                'coupon_id','notes',
                'payment_provider','payment_intent_id',
                'status','completed_at','booking_id'
            ];

            foreach ($assignables as $field) {
                if (array_key_exists($field, $payload)) {
                    $draft->{$field} = $payload[$field];
                }
            }

            // Xử lý passenger_info đặc biệt
            if (isset($payload['passenger_info']) && is_array($payload['passenger_info'])) {
                $draft->passenger_info = array_merge($draft->passenger_info ?? [], $payload['passenger_info']);
            }

            // Xử lý discount_amount
            if (array_key_exists('discount_amount', $payload)) {
                $draft->discount_amount = (int)$payload['discount_amount'];
            }

            // Xử lý payment_expires_at nếu có
            if (array_key_exists('payment_expires_at', $payload)) {
                $draft->payment_expires_at = $payload['payment_expires_at'];
            }

            $draft->save();
            return $draft->fresh(['items']);
        });
    }

    /**
     * Helper demo: tính giá & snapshot label. Thay bằng TripFare/Promotion thực tế của bạn.
     *
     * @param Collection<int,Seat> $seats
     * @return array<int,array{price:int,label:string,fare_id:int|null}>
     */
    protected function computeSeatSnapshots(int $tripId, Collection $seats): array
    {
        $unitPrice = (int) DB::table('trips')
                    ->join('routes', 'trips.route_id', '=', 'routes.id')
                    ->join('trip_stations', 'routes.id', '=', 'trip_stations.route_id')
                    ->where('trips.id', $tripId)
                    ->value('trip_stations.price');        

        $out = [];
        foreach ($seats as $s) {
            $out[$s->id] = [
                'price'   => $unitPrice ?? 0,
                'label'   => $s->label ?? $s->seat_number ?? ('S'.$s->id),
                'fare_id' => null,
            ];
        }
        return $out;
    }
}