<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleTemplateTrip;
use App\Models\Trip;
use Carbon\Carbon;

class GenerateTripsFromTemplateSeeder extends Seeder
{
    /**
     * Generate actual trips from schedule templates
     */
    public function run(): void
    {
        $this->command->info('🚌 Generating trips from schedule templates...');
        
        // Lấy tất cả schedule templates đang active
        $templates = ScheduleTemplateTrip::with(['route', 'bus'])
            ->where('active', true)
            ->get();

        if ($templates->isEmpty()) {
            $this->command->warn('⚠️  No active schedule templates found! Please run ScheduleTemplateTripSeeder first.');
            return;
        }

        $this->command->info("Found {$templates->count()} schedule templates");

        // Tạo trips cho 30 ngày tới
        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays(30);
        
        $createdCount = 0;
        $skippedCount = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $weekday = $date->dayOfWeek; // 0=CN, 1=Th2, ..., 6=Th7
            
            // Lấy templates cho ngày này
            $dayTemplates = $templates->where('weekday', $weekday);
            
            foreach ($dayTemplates as $template) {
                // Tạo departure_time từ date + time
                $departureTime = $date->copy()->setTimeFromTimeString($template->departure_time);
                
                // Kiểm tra xem trip đã tồn tại chưa
                $exists = Trip::where('route_id', $template->route_id)
                    ->where('bus_id', $template->bus_id)
                    ->where('departure_time', $departureTime)
                    ->exists();

                if (!$exists) {
                    try {
                        Trip::create([
                            'route_id' => $template->route_id,
                            'bus_id' => $template->bus_id,
                            'departure_time' => $departureTime,
                            'status' => 'scheduled',
                        ]);
                        
                        $createdCount++;
                        
                        if ($createdCount % 50 == 0) {
                            $this->command->info("Created {$createdCount} trips...");
                        }
                        
                    } catch (\Exception $e) {
                        $this->command->error("Error creating trip: {$e->getMessage()}");
                    }
                } else {
                    $skippedCount++;
                }
            }
        }

        $this->command->info("✅ Trip generation completed!");
        $this->command->info("✓ Created: {$createdCount} trips");
        $this->command->info("✓ Skipped: {$skippedCount} trips (already exist)");
        
        // Hiển thị thống kê
        $this->displayTripSummary();
    }

    /**
     * Hiển thị thống kê trips
     */
    private function displayTripSummary(): void
    {
        $this->command->info("\n📊 TRIP SUMMARY:");
        
        $totalTrips = Trip::count();
        $todayTrips = Trip::whereDate('departure_time', today())->count();
        $tomorrowTrips = Trip::whereDate('departure_time', tomorrow())->count();
        $thisWeekTrips = Trip::whereBetween('departure_time', [now()->startOfWeek(), now()->endOfWeek()])->count();
        
        $this->command->info("  Total trips: {$totalTrips}");
        $this->command->info("  Today: {$todayTrips}");
        $this->command->info("  Tomorrow: {$tomorrowTrips}");
        $this->command->info("  This week: {$thisWeekTrips}");
        
        // Thống kê theo route
        $routeStats = Trip::with('route')
            ->select('route_id', DB::raw('count(*) as trip_count'))
            ->groupBy('route_id')
            ->get();
            
        $this->command->info("\n📈 TRIPS BY ROUTE:");
        foreach ($routeStats as $stat) {
            $routeName = $stat->route->name ?? "Route #{$stat->route_id}";
            $this->command->info("  {$routeName}: {$stat->trip_count} trips");
        }
    }
}
