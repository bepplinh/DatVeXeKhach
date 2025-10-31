<?php

namespace App\Http\Controllers\Client\Checkout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\SeatFlow\SeatLockService;
use App\Services\SeatFlow\SeatReleaseService;

class SeatLockController extends Controller
{
    public function __construct(
        private SeatLockService $seatLock,
        private SeatReleaseService $seatRelease
    ) {}

    /**
     * POST /api/checkout/lock-seats
     * Body:
     */
    public function lock(Request $request)
    {
        $data = $request->validate([
            'trips' => ['required', 'array', 'min:1'],
            'trips.*.trip_id'  => ['required', 'integer', 'min:1'],
            'trips.*.seat_ids' => ['required', 'array', 'min:1'],
            'trips.*.seat_ids.*' => ['required', 'integer', 'min:1'],
            'trips.*.leg'                  => 'nullable|string|in:OUT,RETURN'
        ]);

        $ttl = (int) (SeatLockService::DEFAULT_TTL);
        $token = $request->header('X-Session-Token');
        $userId = Auth::id();

        $result = $this->seatLock->lock(
            trips: $data['trips'],
            sessionToken: $token,
            userId: $userId,
            ttl: $ttl,
        );

        return response()->json($result);
    }

    public function unlock(Request $request)
    {
        $sessionToken = $request->header('X-Session-Token');

        $res = $this->seatRelease->cancelAllBySession($sessionToken);

        return response()->json($res);
    }
}
