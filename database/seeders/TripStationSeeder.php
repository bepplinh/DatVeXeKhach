<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripStationSeeder extends Seeder
{
    public function run(): void
    {
        $routes = DB::table('routes')->get();

        foreach ($routes as $route) {
            // pick first ward of from_city's first district and first ward of to_city's first district
            $fromDistrict = DB::table('locations')->where('type','district')->where('parent_id', $route->from_city)->first();
            $toDistrict   = DB::table('locations')->where('type','district')->where('parent_id', $route->to_city)->first();
            if (!$fromDistrict || !$toDistrict) continue;

            $fromWard = DB::table('locations')->where('type','ward')->where('parent_id', $fromDistrict->id)->first();
            $toWard   = DB::table('locations')->where('type','ward')->where('parent_id', $toDistrict->id)->first();
            if (!$fromWard || !$toWard) continue;

            // Seed two sample price tiers
            DB::table('trip_stations')->insert([
                [
                    'route_id' => $route->id,
                    'from_location_id' => $fromWard->id,
                    'to_location_id'   => $toWard->id,
                    'price' => 100000,
                    'duration_minutes' => 120,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'route_id' => $route->id,
                    'from_location_id' => $fromWard->id,
                    'to_location_id'   => $toWard->id,
                    'price' => 150000,
                    'duration_minutes' => 180,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
} 