<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplateTrip;

class SimpleScheduleTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách routes và buses có sẵn
        $routes = DB::table('routes')->get();
        $buses = DB::table('buses')->get();

        if ($routes->isEmpty() || $buses->isEmpty()) {
            $this->command->warn('Không có routes hoặc buses để tạo lịch trình mẫu!');
            return;
        }

        // Lịch trình cố định: route_id = 1, 7 chuyến/ngày từ thứ 2 đến chủ nhật
        $fixedRouteId = 1; // Route cố định
        
        // 5 giờ khởi hành cố định + 2 giờ bất kỳ
        $fixedDepartureTimes = [
            '06:00:00',  // Sáng sớm
            '08:00:00',  // Sáng
            '12:00:00',  // Trưa
            '16:00:00',  // Chiều
            '20:00:00',  // Tối
        ];
        
        // 2 giờ bất kỳ thêm vào
        $additionalDepartureTimes = [
            '10:00:00',  // Giữa sáng
            '22:00:00',  // Đêm
        ];
        
        // Tổng hợp tất cả giờ khởi hành
        $allDepartureTimes = array_merge($fixedDepartureTimes, $additionalDepartureTimes);
        
        // Tạo lịch trình cho tất cả các ngày (thứ 2 đến chủ nhật)
        $schedules = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $schedules[] = [
                'weekday' => $weekday,
                'departure_times' => $allDepartureTimes
            ];
        }

        $createdCount = 0;

        foreach ($schedules as $schedule) {
            $weekday = $schedule['weekday'];
            
            foreach ($schedule['departure_times'] as $departureTime) {
                // Luân phiên giữa các buses (route_id cố định = 1)
                $busIndex = ($createdCount % $buses->count());
                $bus = $buses[$busIndex];

                // Kiểm tra xem lịch trình này đã tồn tại chưa
                $exists = ScheduleTemplateTrip::where('route_id', $fixedRouteId)
                    ->where('bus_id', $bus->id)
                    ->where('weekday', $weekday)
                    ->where('departure_time', $departureTime)
                    ->exists();

                if (!$exists) {
                    try {
                        ScheduleTemplateTrip::create([
                            'route_id' => $fixedRouteId,
                            'bus_id' => $bus->id,
                            'weekday' => $weekday,
                            'departure_time' => $departureTime,
                            'active' => true,
                        ]);
                        
                        $createdCount++;
                        
                        $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                        $weekdayName = $weekdayNames[$weekday];
                        
                        $this->command->info("✓ Tạo lịch trình: Route ID {$fixedRouteId} - Xe {$bus->code} - {$weekdayName} - {$departureTime}");
                        
                    } catch (\Exception $e) {
                        $this->command->error("✗ Lỗi tạo lịch trình: {$e->getMessage()}");
                    }
                }
            }
        }
    }
}
