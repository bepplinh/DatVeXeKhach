<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;
use App\Models\Seat;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SeatSeeder extends Seeder
{
    public function run(): void
    {
        $buses = Bus::all();

        foreach ($buses as $bus) {
            // chuẩn hoá seat_layout
            $raw = (string) ($bus->seat_layout ?? '');
            $layout = Str::of($raw)->lower()->trim()->toString();

            // chấp nhận nhiều biến thể cho layout "2 tầng A/B"
            $isRoom = in_array($layout, [
                'room', 'double', 'double_decker', 'double-decker'
            ], true);

            if ($isRoom) {
                // nếu muốn đánh số liên tục như ảnh: tổng ghế = 2 tầng * 2 cột * số hàng mỗi cột
                // ví dụ seat_count = 24 -> mỗi tầng 12 ghế -> mỗi cột 6 ghế
                $seatsPerDeck = intdiv($bus->seat_count, 2);
                $seatsPerSide = intdiv($seatsPerDeck, 2);

                // (tuỳ chọn) dọn ghế cũ trước khi tạo lại
                Seat::where('bus_id', $bus->id)->delete();

                foreach (['upper','lower'] as $deckIndex => $deck) {
                    // offset để số ghế chạy liên tục từ tầng trên -> dưới (nếu muốn tầng dưới trước, đảo mảng ở trên)
                    $offset = $deckIndex * $seatsPerSide; // 0 cho upper, +N cho lower

                    // A (left)
                    for ($i = 1; $i <= $seatsPerSide; $i++) {
                        Seat::firstOrCreate(
                            ['bus_id' => $bus->id, 'seat_number' => 'A' . ($i + $offset)],
                            [
                                'deck'          => $deck,
                                'position_meta' => json_encode(['side' => 'left', 'row' => $i]),
                            ]
                        );
                    }
                    // B (right)
                    for ($i = 1; $i <= $seatsPerSide; $i++) {
                        Seat::firstOrCreate(
                            ['bus_id' => $bus->id, 'seat_number' => 'B' . ($i + $offset)],
                            [
                                'deck'          => $deck,
                                'position_meta' => json_encode(['side' => 'right', 'row' => $i]),
                            ]
                        );
                    }
                }

                // Nếu seat_count không chia hết cho 4, bạn có thể xử lý phần dư ở đây (tuỳ quy tắc):
                // ví dụ: thêm vào tầng dưới trước, cột A trước, ...
            } else {
                // layout thường -> S1..Sn
                for ($i = 1; $i <= $bus->seat_count; $i++) {
                    Seat::firstOrCreate(
                        ['bus_id' => $bus->id, 'seat_number' => 'S' . $i],
                        ['deck' => null, 'position_meta' => null]
                    );
                }
            }
        }
    }
}
