<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $routes = DB::table('routes')->get();
        $buses = DB::table('buses')->get();

        // Kiểm tra có buses không
        if ($buses->isEmpty()) {
            $this->command->warn('⚠️  No buses found! Please run BusSeeder first.');
            return;
        }

        foreach ($routes as $route) {
            // Tạo 3-4 chuyến cho mỗi route với bus khác nhau
            $times = [
                now()->addDays(1)->setTime(8, 0),   // 8:00 sáng mai
                now()->addDays(1)->setTime(14, 30), // 2:30 chiều mai
                now()->addDays(2)->setTime(6, 30),  // 6:30 sáng ngày kia
                now()->addDays(2)->setTime(20, 0),  // 8:00 tối ngày kia
            ];

            foreach ($times as $index => $time) {
                $bus = $buses->get($index % $buses->count()); // Chọn bus theo vòng lặp
                
                DB::table('trips')->insert([
                    'route_id'       => $route->id,
                    'bus_id'         => $bus->id,
                    'departure_time' => $time,
                    'status'         => 'scheduled',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }
}
