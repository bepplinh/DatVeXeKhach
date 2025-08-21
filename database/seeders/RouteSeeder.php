<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        // Define some city pairs by name (must exist in LocationSeeder as type=city)
        $pairs = [
            ['from' => 'Hà Nội', 'to' => 'Thanh Hóa'],
            ['from' => 'Hà Nam', 'to' => 'Ninh Bình'],
            ['from' => 'Nam Định', 'to' => 'Thanh Hóa'],
        ];

        foreach ($pairs as $p) {
            $fromId = DB::table('locations')->where('name', $p['from'])->where('type','city')->value('id');
            $toId   = DB::table('locations')->where('name', $p['to'])->where('type','city')->value('id');
            if (!$fromId || !$toId) {
                continue;
            }

            // Forward
            $existsFwd = DB::table('routes')->where('from_city', $fromId)->where('to_city', $toId)->exists();
            if (!$existsFwd) {
                DB::table('routes')->insert([
                    'from_city' => $fromId,
                    'to_city'   => $toId,
                    'name'      => $p['from'] . ' - ' . $p['to'],
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]);
            }

            // Reverse
            $existsRev = DB::table('routes')->where('from_city', $toId)->where('to_city', $fromId)->exists();
            if (!$existsRev) {
                DB::table('routes')->insert([
                    'from_city' => $toId,
                    'to_city'   => $fromId,
                    'name'      => $p['to'] . ' - ' . $p['from'],
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]);
            }
        }
    }
}
