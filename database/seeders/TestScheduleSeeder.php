<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplateTrip;

class TestScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy route và bus đầu tiên để test
        $route = DB::table('routes')->first();
        $bus = DB::table('buses')->first();

        if (!$route || !$bus) {
            $this->command->warn('Không có routes hoặc buses để test!');
            return;
        }

        // Tạo lịch trình test đơn giản
        $testSchedules = [
            [
                'weekday' => 1, // Thứ 2
                'departure_time' => '08:00:00',
                'description' => 'Thứ 2 sáng'
            ],
            [
                'weekday' => 1, // Thứ 2
                'departure_time' => '18:00:00',
                'description' => 'Thứ 2 chiều'
            ],
            [
                'weekday' => 5, // Thứ 6
                'departure_time' => '20:00:00',
                'description' => 'Thứ 6 tối'
            ],
            [
                'weekday' => 0, // Chủ nhật
                'departure_time' => '10:00:00',
                'description' => 'Chủ nhật sáng'
            ],
        ];

        $createdCount = 0;

        foreach ($testSchedules as $schedule) {
            // Kiểm tra xem lịch trình này đã tồn tại chưa
            $exists = ScheduleTemplateTrip::where('route_id', $route->id)
                ->where('bus_id', $bus->id)
                ->where('weekday', $schedule['weekday'])
                ->where('departure_time', $schedule['departure_time'])
                ->exists();

            if (!$exists) {
                try {
                    ScheduleTemplateTrip::create([
                        'route_id' => $route->id,
                        'bus_id' => $bus->id,
                        'weekday' => $schedule['weekday'],
                        'departure_time' => $schedule['departure_time'],
                        'active' => true,
                    ]);
                    
                    $createdCount++;
                    
                    $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                    $weekdayName = $weekdayNames[$schedule['weekday']];
                    
                    $this->command->info("✓ Test: {$route->name} - Xe {$bus->code} - {$weekdayName} - {$schedule['departure_time']} ({$schedule['description']})");
                    
                } catch (\Exception $e) {
                    $this->command->error("✗ Lỗi test: {$e->getMessage()}");
                }
            } else {
                $this->command->warn("⚠ Bỏ qua: Lịch trình đã tồn tại");
            }
        }

        $this->command->info("Test seeder hoàn thành!");
        $this->command->info("✓ Đã tạo: {$createdCount} lịch trình test");
        
        // Hiển thị thống kê
        $total = ScheduleTemplateTrip::count();
        $this->command->info("📊 Tổng số lịch trình trong DB: {$total}");
    }
}

