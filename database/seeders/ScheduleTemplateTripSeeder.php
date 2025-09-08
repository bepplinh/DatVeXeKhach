<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplateTrip;

class ScheduleTemplateTripSeeder extends Seeder
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

        // Định nghĩa lịch trình mẫu từ thứ 2 đến chủ nhật
        $schedules = [
            // Thứ 2 (weekday = 1)
            [
                'weekday' => 1,
                'departure_times' => ['06:00:00', '08:00:00', '10:00:00', '14:00:00', '16:00:00', '18:00:00', '20:00:00']
            ],
            // Thứ 3 (weekday = 2)
            [
                'weekday' => 2,
                'departure_times' => ['06:30:00', '08:30:00', '10:30:00', '14:30:00', '16:30:00', '18:30:00', '20:30:00']
            ],
            // Thứ 4 (weekday = 3)
            [
                'weekday' => 3,
                'departure_times' => ['07:00:00', '09:00:00', '11:00:00', '15:00:00', '17:00:00', '19:00:00', '21:00:00']
            ],
            // Thứ 5 (weekday = 4)
            [
                'weekday' => 4,
                'departure_times' => ['07:30:00', '09:30:00', '11:30:00', '15:30:00', '17:30:00', '19:30:00', '21:30:00']
            ],
            // Thứ 6 (weekday = 5)
            [
                'weekday' => 5,
                'departure_times' => ['08:00:00', '10:00:00', '12:00:00', '16:00:00', '18:00:00', '20:00:00', '22:00:00']
            ],
            // Thứ 7 (weekday = 6)
            [
                'weekday' => 6,
                'departure_times' => ['08:30:00', '10:30:00', '12:30:00', '16:30:00', '18:30:00', '20:30:00', '22:30:00']
            ],
            // Chủ nhật (weekday = 0)
            [
                'weekday' => 0,
                'departure_times' => ['09:00:00', '11:00:00', '13:00:00', '17:00:00', '19:00:00', '21:00:00', '23:00:00']
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($schedules as $schedule) {
            $weekday = $schedule['weekday'];
            
            foreach ($schedule['departure_times'] as $departureTime) {
                // Luân phiên giữa các routes và buses để tạo đa dạng
                $routeIndex = ($createdCount % $routes->count());
                $busIndex = ($createdCount % $buses->count());
                
                $route = $routes[$routeIndex];
                $bus = $buses[$busIndex];

                // Kiểm tra xem lịch trình này đã tồn tại chưa
                $exists = ScheduleTemplateTrip::where('route_id', $route->id)
                    ->where('bus_id', $bus->id)
                    ->where('weekday', $weekday)
                    ->where('departure_time', $departureTime)
                    ->exists();

                if (!$exists) {
                    try {
                        ScheduleTemplateTrip::create([
                            'route_id' => $route->id,
                            'bus_id' => $bus->id,
                            'weekday' => $weekday,
                            'departure_time' => $departureTime,
                            'active' => true,
                        ]);
                        
                        $createdCount++;
                        
                        $this->command->info("✓ Tạo lịch trình: {$route->name} - Xe {$bus->code} - Thứ {$weekday} - {$departureTime}");
                        
                    } catch (\Exception $e) {
                        $this->command->error("✗ Lỗi tạo lịch trình: {$e->getMessage()}");
                    }
                } else {
                    $skippedCount++;
                }
            }
        }

        $this->command->info("Seeder hoàn thành!");
        $this->command->info("✓ Đã tạo: {$createdCount} lịch trình mẫu");
        $this->command->info("✓ Đã bỏ qua: {$skippedCount} lịch trình (đã tồn tại)");
        
        // Hiển thị thống kê theo ngày
        $this->displayScheduleSummary();
    }

    /**
     * Hiển thị thống kê lịch trình theo ngày
     */
    private function displayScheduleSummary(): void
    {
        $this->command->info("\n📊 THỐNG KÊ LỊCH TRÌNH THEO NGÀY:");
        
        $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $count = ScheduleTemplateTrip::where('weekday', $weekday)->count();
            $weekdayName = $weekdayNames[$weekday];
            
            $this->command->info("  {$weekdayName}: {$count} chuyến");
        }
        
        $total = ScheduleTemplateTrip::count();
        $this->command->info("  Tổng cộng: {$total} chuyến");
    }
}
