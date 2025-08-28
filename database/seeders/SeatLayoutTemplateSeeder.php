<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeatLayoutTemplateSeeder extends Seeder
{
    private array $labelPrefix = ['right' => 'A', 'middle' => 'C', 'left' => 'B'];

    public function run(): void
    {
        $templates = [
            // 24 giường (2 tầng, 2 cột: 6+6 mỗi tầng)
            [
                'code'  => 'SL-24-2D',
                'name'  => 'Giường 24 (2 tầng, 2 cột)',
                'decks' => 2,
                'plan'  => [
                    1 => ['right' => 6, 'left' => 6], // tầng 1: A7..A12, B7..B12
                    2 => ['right' => 6, 'left' => 6], // tầng 2: A1..A6, B1..B6
                ],
                'offset' => [1 => 6, 2 => 0],
            ],
            // 22 phòng (2 tầng, 2 cột: tầng1:5+5, tầng2:6+6)
            [
                'code'  => 'SL-22-2D',
                'name'  => 'Phòng 22 (2 tầng, 2 cột)',
                'decks' => 2,
                'plan'  => [
                    1 => ['right' => 5, 'left' => 5], // tầng 1: A7..A11, B7..B11
                    2 => ['right' => 6, 'left' => 6], // tầng 2: A1..A6, B1..B6
                ],
                'offset' => [1 => 6, 2 => 0],
            ],
            // 24 ghế ngồi (1 tầng, 2 cột: 12+12)
            [
                'code'  => 'SL-24-1D',
                'name'  => 'Ghế ngồi 24 (1 tầng, 2 cột)',
                'decks' => 1,
                'plan'  => [ 1 => ['right' => 12, 'left' => 12] ],
                'offset' => [1 => 0],
            ],
            // 16 ghế ngồi (1 tầng, 2 cột: 8+8)
            [
                'code'  => 'SL-16-1D',
                'name'  => 'Ghế ngồi 16 (1 tầng, 2 cột)',
                'decks' => 1,
                'plan'  => [ 1 => ['right' => 8, 'left' => 8] ],
                'offset' => [1 => 0],
            ],
            // 8 ghế ngồi (1 tầng, 2 cột: 4+4)
            [
                'code'  => 'SL-8-1D',
                'name'  => 'Ghế ngồi 8 (1 tầng, 2 cột)',
                'decks' => 1,
                'plan'  => [ 1 => ['right' => 4, 'left' => 4] ],
                'offset' => [1 => 0],
            ],
            // 44 giường (2 tầng, 3 cột: 8+6+8 mỗi tầng)
            [
                'code'  => 'SL-44-3C',
                'name'  => 'Giường 44 (2 tầng, 3 cột)',
                'decks' => 2,
                'plan'  => [
                    1 => ['right' => 8, 'middle' => 6, 'left' => 8],
                    2 => ['right' => 8, 'middle' => 6, 'left' => 8],
                ],
                'offset' => [1 => 0, 2 => 0],
            ],
        ];

        foreach ($templates as $tpl) {
            // insert vào bảng seat_layout_templates
            $total = 0;
            foreach ($tpl['plan'] as $cols) {
                $total += array_sum($cols);
            }

            $tplId = DB::table('seat_layout_templates')->insertGetId([
                'code'        => $tpl['code'],
                'name'        => $tpl['name'],
                'decks'       => $tpl['decks'],
                'total_seats' => $total,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // insert vào bảng template_seats
            foreach ($tpl['plan'] as $deck => $cols) {
                foreach (['right','middle','left'] as $group) {
                    $n = $cols[$group] ?? 0;
                    if ($n <= 0) continue;

                    $prefix = $this->labelPrefix[$group];
                    $offset = $tpl['offset'][$deck] ?? 0;

                    for ($i = 1; $i <= $n; $i++) {
                        $labelNum = $i + $offset;
                        $seatLabel = $prefix . str_pad($labelNum, 2, '0', STR_PAD_LEFT);

                        DB::table('template_seats')->insert([
                            'seat_layout_template_id' => $tplId,
                            'deck'            => $deck,
                            'column_group'    => $group,
                            'index_in_column' => $i,
                            'seat_label'      => $seatLabel,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }
                }
            }
        }
    }
}
