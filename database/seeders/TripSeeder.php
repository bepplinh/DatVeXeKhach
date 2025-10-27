<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Đảm bảo sử dụng Carbon

class TripSeeder extends Seeder
{
    /**
     * Chạy các seeder database để chèn 2 chuyến cố định.
     */
    public function run(): void
    {
        // 1. Tính toán thời gian khởi hành (Ngày mai lúc 8:00 và 14:30)
        $tomorrow_morning = Carbon::now()->addDays(1)->setTime(8, 0);
        $tomorrow_afternoon = Carbon::now()->addDays(1)->setTime(14, 30);
        
        // 2. Định nghĩa dữ liệu cần chèn
        $trips_to_seed = [
            [
                // Chuyến 1
                'route_id'       => 1, // Route ID cố định
                'bus_id'         => 1, // Bus ID cố định
                'departure_time' => $tomorrow_morning,
                'status'         => 'scheduled',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                // Chuyến 2
                'route_id'       => 2, // Route ID cố định
                'bus_id'         => 2, // Bus ID cố định
                'departure_time' => $tomorrow_afternoon,
                'status'         => 'scheduled',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        // 3. Xóa dữ liệu cũ (tùy chọn, để tránh trùng lặp nếu chạy lại seeder)
        // Lưu ý: Nếu cột 'trip_id' là auto-increment, bạn không cần chèn nó.
        // Tôi sẽ chèn dữ liệu mà không cần chỉ định 'trip_id', để nó tự động tăng.
        // Tuy nhiên, nếu bạn muốn đảm bảo trip_id là 1 và 2, bạn cần reset hoặc xử lý khóa chính.
        
        // Giả định 'id' (trip_id) là auto-increment và chỉ chèn các trường dữ liệu.

        DB::table('trips')->insert($trips_to_seed);

        $this->command->info('✅ Seeded 2 fixed trips (Route 1/Bus 1 and Route 2/Bus 2) for tomorrow.');
    }
}
