<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplateTrip;
use Carbon\Carbon;

class ScheduleTemplateTripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ==== Cấu hình nhanh ====
        $firstDeparture  = '08:00:00'; // giờ xuất phát chuyến đầu tiên trong ngày
        $tripsPerDay     = 5;          // số chuyến mỗi ngày
        $intervalMinutes = 60;         // khoảng cách giữa các chuyến (phút)
        // ========================

        // Lấy danh sách routes và buses có sẵn
        $routes = DB::table('routes')->get();
        $buses  = DB::table('buses')->get();

        if ($routes->isEmpty() || $buses->isEmpty()) {
            $this->command->warn('Không có routes hoặc buses để tạo lịch trình mẫu!');
            return;
        }

        // Tạo danh sách giờ xuất phát cho mọi ngày (giống nhau)
        $departureTimes = [];
        $start = Carbon::createFromTimeString($firstDeparture);
        for ($i = 0; $i < $tripsPerDay; $i++) {
            $departureTimes[] = $start->copy()->addMinutes($intervalMinutes * $i)->format('H:i:s');
        }

        $weekdays = range(0, 6); // 0: Chủ nhật, 1..6: Thứ 2..Thứ 7

        $createdCount = 0;
        $skippedCount = 0;

        // Con trỏ round-robin để phân phối route/bus đều nhau
        $routeIdx = 0;
        $busIdx   = 0;
        $routeCnt = $routes->count();
        $busCnt   = $buses->count();

        foreach ($weekdays as $weekday) {
            foreach ($departureTimes as $time) {
                $route = $routes[$routeIdx];
                $bus   = $buses[$busIdx];

                // Kiểm tra tồn tại
                $exists = ScheduleTemplateTrip::where('route_id', $route->id)
                    ->where('bus_id', $bus->id)
                    ->where('weekday', $weekday)
                    ->where('departure_time', $time)
                    ->exists();

                if (!$exists) {
                    try {
                        ScheduleTemplateTrip::create([
                            'route_id'       => $route->id,
                            'bus_id'         => $bus->id,
                            'weekday'        => $weekday,
                            'departure_time' => $time,
                            'active'         => true,
                        ]);

                        $createdCount++;
                        $this->command->info("✓ Tạo lịch: {$route->name} - Xe {$bus->code} - weekday {$weekday} - {$time}");
                    } catch (\Throwable $e) {
                        $this->command->error("✗ Lỗi tạo lịch: {$e->getMessage()}");
                    }
                } else {
                    $skippedCount++;
                }

                // Tăng con trỏ round-robin (kể cả khi skip để giữ phân phối đều)
                $routeIdx = ($routeIdx + 1) % $routeCnt;
                $busIdx   = ($busIdx + 1) % $busCnt;
            }
        }

        $this->displayScheduleSummary();
    }

    /**
     * Hiển thị thống kê lịch trình theo ngày
     */
    private function displayScheduleSummary(): void
    {
        $weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $count = ScheduleTemplateTrip::where('weekday', $weekday)->count();
            $weekdayName = $weekdayNames[$weekday];
            $this->command->info("  {$weekdayName}: {$count} chuyến");
        }

        $total = ScheduleTemplateTrip::count();
    }
}
