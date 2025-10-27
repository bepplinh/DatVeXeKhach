<?php


namespace App\Services\DraftCheckoutService;

use App\Models\Seat;
use App\Models\Trip;
use App\Models\DraftCheckout;
use App\Models\DraftCheckoutLeg;
use App\Models\DraftCheckoutItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class DraftCheckoutService
{
    public function createFromLocks(
        array $seatsByTrip,
        array $legsByTrip,
        string $token,
        ?int $userId,
        int $ttlSeconds
    ): array {
        if (empty($seatsByTrip)) {
            throw ValidationException::withMessages(['trips' => ['Không có ghế hợp lệ.']]);
        }

        // 2) Load Trip + Seat
        $tripInfo = $this->loadTripsAndSeats($seatsByTrip);

        // 3) Tính giá theo logic của bạn (computeSeatSnapshots)
        $snapshotsByTrip = []; // [tripId => [ [seat_id, seat_code, price], ... ]]
        foreach ($tripInfo as $tripId => $info) {
            $seats = $info['seats']->only($seatsByTrip[$tripId])->values();
            $snapshotsByTrip[$tripId] = $this->computeSeatSnapshots((int)$tripId, $seats);
        }

        // 4) Transaction tạo/cập nhật draft + legs + items
        $result = DB::transaction(function () use ($seatsByTrip, $legsByTrip, $snapshotsByTrip, $token, $userId, $ttlSeconds) {

            // 4.1 Upsert draft theo session_token (idempotent)
            /** @var DraftCheckout|null $draft */
            $draft = DraftCheckout::query()
                ->where('session_token', $token)
                ->whereIn('status', ['pending', 'paying'])
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$draft) {
                $draft = DraftCheckout::query()->create([
                    'user_id'        => $userId,
                    'session_token'  => $token,
                    'status'         => 'pending',
                    'currency'       => 'VND',
                    'total_price'    => 0,
                    'discount_amount' => 0,
                    'expires_at'     => now()->addSeconds($ttlSeconds),
                ]);
            } else {
                // gia hạn thời gian theo TTL mới nhất
                $draft->update([
                    'expires_at' => now()->addSeconds($ttlSeconds),
                ]);
            }

            // 4.2 Đảm bảo legs tồn tại cho từng trip
            $legMap = []; // [tripId => DraftCheckoutLeg]
            $existingLegs = DraftCheckoutLeg::query()
                ->where('draft_checkout_id', $draft->id)
                ->whereIn('trip_id', array_keys($seatsByTrip))
                ->get()
                ->keyBy('trip_id');

            $newLegRows = [];
            $now = now();
            foreach ($seatsByTrip as $tripId => $_) {
                if (isset($existingLegs[$tripId])) {
                    $legMap[$tripId] = $existingLegs[$tripId];
                } else {
                    $newLegRows[] = [
                        'draft_checkout_id' => $draft->id,
                        'trip_id'           => $tripId,
                        'leg'               => $legsByTrip[$tripId] ?? null,
                        'total_price'       => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }
            if (!empty($newLegRows)) {
                DraftCheckoutLeg::insert($newLegRows);
                $inserted = DraftCheckoutLeg::query()
                    ->where('draft_checkout_id', $draft->id)
                    ->whereIn('trip_id', array_keys($seatsByTrip))
                    ->get()
                    ->keyBy('trip_id');
                foreach ($inserted as $tripId => $leg) {
                    $legMap[$tripId] = $leg;
                }
            }

            // 4.3 (Idempotent) Xoá items cũ của các legs “mục tiêu”, rồi insert mới
            $legIds = array_map(fn($l) => $l->id, $legMap);
            if (!empty($legIds)) {
                DraftCheckoutItem::query()
                    ->whereIn('draft_checkout_leg_id', $legIds)
                    ->delete();
            }

            $itemRows = [];
            $subtotalDraft = 0;
            foreach ($seatsByTrip as $tripId => $seatIds) {
                $leg   = $legMap[$tripId];
                $snap  = $snapshotsByTrip[$tripId]; // all seats of this trip (already filtered)
                foreach ($snap as $s) {
                    if (!in_array($s['seat_id'], $seatIds, true)) continue; // safety
                    $price = (int)$s['price'];
                    $itemRows[] = [
                        'draft_checkout_id'     => $draft->id,
                        'draft_checkout_leg_id' => $leg->id,
                        'trip_id'               => $tripId,
                        'seat_id'               => $s['seat_id'],
                        'seat_label'            => $s['seat_code'],
                        'price'                 => $price,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ];
                    $subtotalDraft += $price;
                }
            }

            if (!empty($itemRows)) {
                DraftCheckoutItem::insert($itemRows);
            }

            // 4.4 Cập nhật totals từng leg
            $legTotals = DraftCheckoutItem::query()
                ->selectRaw('draft_checkout_leg_id, SUM(price) as sum_price')
                ->whereIn('draft_checkout_leg_id', $legIds)
                ->groupBy('draft_checkout_leg_id')
                ->pluck('sum_price', 'draft_checkout_leg_id');

            foreach ($legMap as $tripId => $leg) {
                $sum = (int)($legTotals[$leg->id] ?? 0);
                $leg->update([
                    'subtotal_price' => $sum,
                    'discount_amount' => 0,
                    'total_price'    => $sum,
                ]);
            }

            // 4.5 Cập nhật totals draft
            $discountDraft = 0; // TODO: áp dụng coupon nếu có
            $totalDraft    = max(0, $subtotalDraft - $discountDraft);
            $draft->update([
                'total_price'     => $totalDraft,
                'discount_amount' => $discountDraft,
            ]);

            // Chuẩn bị response
            $items = DraftCheckoutItem::query()
                ->where('draft_checkout_id', $draft->id)
                ->orderBy('draft_checkout_leg_id')
                ->get();

            // Gắn leg code (OUT/RETURN) cho từng item
            $legCodeById = [];
            foreach ($legMap as $tripId => $leg) {
                $legCodeById[$leg->id] = $legsByTrip[$tripId] ?? $leg->leg;
            }

            return [
                'draft_id'   => $draft->id,
                'status'     => $draft->status,
                'expires_at' => $draft->expires_at?->toDateTimeString(),
                'totals'     => [
                    'subtotal' => $subtotalDraft,
                    'discount' => $discountDraft,
                    'total'    => $totalDraft,
                ],
                'items' => $items->map(function ($it) use ($legCodeById) {
                    return [
                        'trip_id'    => (int)$it->trip_id,
                        'leg'        => $legCodeById[$it->draft_checkout_leg_id] ?? null,
                        'seat_id'    => (int)$it->seat_id,
                        'seat_label' => $it->seat_label,
                        'price'      => (int)$it->price,
                    ];
                })->values()->all(),
            ];
        });

        return $result;
    }

    private function loadTripsAndSeats(array $seatsByTrip)
    {
        $tripIds = array_keys($seatsByTrip);
        $trips = Trip::query()
            ->whereIn('id', $tripIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($seatsByTrip as $tripId => $seatIds) {
            $trip = $trips[$tripId] ?? null;
            if (!$trip) {
                throw ValidationException::withMessages([
                    'trips' => ["Trip {$tripId} không tồn tại."],
                ]);
            }

            $seats = Seat::query()
                ->whereIn('id', $seatIds)
                ->get()
                ->keyBy('id');

            foreach ($seatIds as $seatId) {
                if (!isset($seats[$seatId])) {
                    throw ValidationException::withMessages([
                        'seats' => ["Seat {$seatId} không tồn tại trong trip {$tripId}."],
                    ]);
                }
            }

            $result[$tripId] = ['trip' => $trip, 'seats' => $seats];
        }
        return $result;
    }

    protected function computeSeatSnapshots(int $tripId, Collection $seats)
    {
        $unitPrice = (int) DB::table('trips')
            ->join('routes', 'trips.route_id', '=', 'routes.id')
            ->join('trip_stations', 'routes.id', '=', 'trip_stations.route_id')
            ->where('trips.id', $tripId)
            ->value('trip_stations.price');

        $out = [];
        foreach ($seats as $seat) {
            $s = Seat::find($seat->id);
            $out[] = [
                'seat_id'    => $seat->id,
                'seat_code'  => $s->seat_number,
                'price' => $unitPrice,
            ];
        }

        return $out;
    }
}
