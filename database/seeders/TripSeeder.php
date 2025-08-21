<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $routes = DB::table('routes')->get();

        foreach ($routes as $route) {
            DB::table('trips')->insert([
                [
                    'route_id'       => $route->id,
                    'bus_id'         => null,
                    'departure_time' => now()->addDays(1)->setTime(8, 0),
                    'status'         => 'scheduled',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ],
                [
                    'route_id'       => $route->id,
                    'bus_id'         => null,
                    'departure_time' => now()->addDays(2)->setTime(15, 30),
                    'status'         => 'scheduled',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ],
            ]);
        }
    }
}
