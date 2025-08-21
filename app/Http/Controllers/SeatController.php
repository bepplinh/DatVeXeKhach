<?php

namespace App\Http\Controllers;

use App\Models\Seat;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    /**
     * Trả về seat map theo busId, nhóm theo deck (upper/lower) và cột A/B.
     * Hỗ trợ cả 2 kiểu schema:
     *  - Có cột 'side' và 'row'
     *  - Hoặc lưu JSON ở 'position_meta' = {"side":"left|right","row":N}
     */
    public function show(Request $request, int $busId)
    {
        $seats = Seat::where('bus_id', $busId)
            ->orderByRaw("FIELD(deck,'upper','lower')") // upper trước
            ->orderBy('id') // fallback khi thiếu 'row'
            ->get()
            ->map(function ($s) {
                // Lấy side/row từ cột tường minh hoặc từ JSON position_meta
                $side = $s->side ?? null;
                $row  = $s->row  ?? null;

                if (!$side || !$row) {
                    $meta = $s->position_meta ? json_decode($s->position_meta, true) : [];
                    $side = $side ?? ($meta['side'] ?? null);
                    $row  = $row  ?? ($meta['row']  ?? null);
                }

                // Suy ra cột A/B từ side nếu cần
                $col = null;
                if ($side === 'left')  $col = 'A';
                if ($side === 'right') $col = 'B';

                return [
                    'id'          => $s->id,
                    'seat_number' => $s->seat_number, // ví dụ A7/B7...
                    'deck'        => $s->deck,        // upper/lower
                    'side'        => $side,           // left/right
                    'col'         => $col,            // A/B
                    'row'         => $row,            // 1..N
                    'status'      => $s->status ?? 'available', // nếu có
                ];
            });

        // Nhóm theo deck rồi tách 2 cột A/B. Sắp theo 'row' nếu có.
        $byDeck = $seats->groupBy('deck');

        $formatDeck = function ($items) {
            $A = $items->where('col','A')->sortBy('row')->values()->all();
            $B = $items->where('col','B')->sortBy('row')->values()->all();
            return ['A' => $A, 'B' => $B];
        };

        $upper = $formatDeck($byDeck->get('upper', collect()));
        $lower = $formatDeck($byDeck->get('lower', collect()));

        return response()->json([
            'bus_id' => $busId,
            'decks'  => [
                'upper' => $upper,  // { A: [...], B: [...] }
                'lower' => $lower,
            ],
        ]);
    }
}
