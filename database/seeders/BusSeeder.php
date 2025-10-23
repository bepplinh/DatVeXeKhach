<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Bus;

class BusSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed type_buses
        $types = [
            ['name' => 'Giường nằm', 'seat_count' => 38],
            ['name' => 'Limousine',  'seat_count' => 24],
            ['name' => 'Ghế ngồi',   'seat_count' => 45],
        ];

        foreach ($types as $t) {
            DB::table('type_buses')->updateOrInsert(
                ['name' => $t['name']],
                [
                    'seat_count' => $t['seat_count'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // 2. Map type name -> id
        $typeId = fn(string $name) => DB::table('type_buses')->where('name', $name)->value('id');

        // 3. Map layout name -> id (giả sử bạn có sẵn các template)
        $layoutId = fn(string $name) => DB::table('seat_layout_templates')->where('name', $name)->value('id');

        // 4. Danh sách bus kèm layout tương ứng
        $buses = [
            [
                'code'                   => 'BUS001',
                'name'                   => 'Xe giường nằm A',
                'plate_number'           => '51A-12345',
                'type_bus_id'            => $typeId('Giường nằm'),
                'seat_layout_template_id'=> $layoutId('Giường nằm 38 chỗ'),
            ],
            [
                'code'                   => 'BUS002',
                'name'                   => 'Xe limousine B',
                'plate_number'           => '51B-67890',
                'type_bus_id'            => $typeId('Limousine'),
                'seat_layout_template_id'=> $layoutId('Limousine 24 chỗ'),
            ],
            [
                'code'                   => 'BUS003',
                'name'                   => 'Xe ghế ngồi C',
                'plate_number'           => '51C-11223',
                'type_bus_id'            => $typeId('Ghế ngồi'),
                'seat_layout_template_id'=> $layoutId('Ghế ngồi 45 chỗ'),
            ],
            [
                'code'                   => 'BUS004',
                'name'                   => 'Xe giường nằm D',
                'plate_number'           => '51D-44556',
                'type_bus_id'            => $typeId('Giường nằm'),
                'seat_layout_template_id'=> $layoutId('Giường nằm 38 chỗ'),
            ],
            [
                'code'                   => 'BUS005',
                'name'                   => 'Xe limousine E',
                'plate_number'           => '51E-77889',
                'type_bus_id'            => $typeId('Limousine'),
                'seat_layout_template_id'=> $layoutId('Limousine 24 chỗ'),
            ],
        ];

        // 5. Insert hoặc update
        foreach ($buses as $bus) {
            $existing = Bus::where('code', $bus['code'])
                ->orWhere('plate_number', $bus['plate_number'])
                ->first();

            if ($existing) {
                $existing->update($bus);
            } else {
                Bus::create($bus);
            }
        }
    }
}
