<?php

namespace App\Http\Controllers\Client\Checkout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\SeatFlow\SeatCheckoutService;

class SeatLockController extends Controller
{
    public function __construct(
        private SeatCheckoutService $seatCheckout
    ) {}
    
    /**
     * POST /api/checkout/lock-seats
     * Body:
     * {
     *   "trips": [
     *     {"trip_id": 1, "seat_ids": [3,4,5]},
     *     {"trip_id": 2, "seat_ids": [1,2]}
     *   ],
     *   "token": "sess_abc123",
     *   "ttl": 180
     * }
     */
    public function lock(Request $request)
    {
        $data = $request->validate([
            'trips' => ['required', 'array', 'min:1'],
            'trips.*.trip_id'  => ['required', 'integer', 'min:1'],
            'trips.*.seat_ids' => ['required', 'array', 'min:1'],
            'trips.*.seat_ids.*' => ['required', 'integer', 'min:1'],
            'trips.*.leg'                  => 'nullable|string|in:OUT,RETURN',
            'ttl'                     => 'nullable|integer|min:30|max:3600',
        ]);

        $ttl = (int) (SeatCheckoutService::DEFAULT_TTL);
        $token = $request->header('X-Session-Token');
        $userId = Auth::id();

        $result = $this->seatCheckout->checkout(
            trips: $data['trips'],
            sessionToken: $token,
            userId: $userId,
            ttl: $ttl,
        );

        return response()->json($result);
    }
}