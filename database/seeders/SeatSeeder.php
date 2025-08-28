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
        for ($i =1; $i <= 6; $i++) {
            Seat::create([
                'bus_id' => 1,
                'seat_number' => 'A' . $i,
                'deck' => 1,
                'column_group' => 'left',
                'index_in_column' => $i,
            ]);

            Seat::create([
                'bus_id' => 1,
                'seat_number' => 'B'.$i,   // trái
                'deck' => 1,
                'column_group' => 'right',
                'index_in_column' => $i,
            ]);
        }
    }
}
