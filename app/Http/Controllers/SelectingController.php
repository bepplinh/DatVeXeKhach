<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\SeatSelecting;
use App\Events\SeatUnselecting;

class SelectingController extends Controller
{
    public function select(Request $request, int $tripId)
    {
        $request->validate(['seat_id' => ['required','integer','min:1']]);
        $token = $request->header('X-Select-Token') ?: bin2hex(random_bytes(8));

        event(new SeatSelecting(
            tripId: $tripId,
            seatIds: (int)$request->seat_id,
            byToken: $token,
            byUserId: optional($request->user())->id
        ));

        return response()->json(['ok' => true, 'select_token' => $token]);
    }

    public function unselect(Request $request, int $tripId, int $seatId)
    {
        $token = $request->header('X-Select-Token');
        if ($token) event(new SeatUnselecting($tripId, $seatId, $token));
        return response()->json(['ok' => true]);
    }
}
