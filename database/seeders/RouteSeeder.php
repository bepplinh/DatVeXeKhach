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
            // Routes từ Hà Nội
            ['from' => 'Hà Nội', 'to' => 'Thanh Hóa'],
            ['from' => 'Hà Nội', 'to' => 'Ninh Bình'],
            ['from' => 'Hà Nội', 'to' => 'Nam Định'],
            ['from' => 'Hà Nội', 'to' => 'TP.HCM'],
            ['from' => 'Hà Nội', 'to' => 'Đà Nẵng'],
            ['from' => 'Hà Nội', 'to' => 'Huế'],
            
            // Routes từ TP.HCM
            ['from' => 'TP.HCM', 'to' => 'Đà Nẵng'],
            ['from' => 'TP.HCM', 'to' => 'Huế'],
            ['from' => 'TP.HCM', 'to' => 'Hà Nội'],
            
            // Routes từ Đà Nẵng
            ['from' => 'Đà Nẵng', 'to' => 'Huế'],
            ['from' => 'Đà Nẵng', 'to' => 'Hà Nội'],
            ['from' => 'Đà Nẵng', 'to' => 'TP.HCM'],
            
            // Routes từ Huế
            ['from' => 'Huế', 'to' => 'Đà Nẵng'],
            ['from' => 'Huế', 'to' => 'Hà Nội'],
            ['from' => 'Huế', 'to' => 'TP.HCM'],
            
            // Routes nội bộ miền Bắc
            ['from' => 'Hà Nam', 'to' => 'Ninh Bình'],
            ['from' => 'Nam Định', 'to' => 'Thanh Hóa'],
            ['from' => 'Thanh Hóa', 'to' => 'Ninh Bình'],
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
