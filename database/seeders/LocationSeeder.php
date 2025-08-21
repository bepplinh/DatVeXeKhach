<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // 1. Hà Nội
            'Hà Nội' => [
                'Ba Đình' => ['Kim Mã', 'Ngọc Hà'],
                'Hoàn Kiếm' => ['Hàng Trống', 'Tràng Tiền'],
            ],

            // 2. Hà Nam
            'Hà Nam' => [
                'Thành phố Phủ Lý' => ['Hai Bà Trưng', 'Lê Hồng Phong'],
                'Kim Bảng' => ['Hoàng Đông', 'Đại Cương'],
            ],

            // 3. Ninh Bình
            'Ninh Bình' => [
                'Thành phố Ninh Bình' => ['Đông Thành', 'Nam Bình'],
                'Gia Viễn' => ['Gia Vân', 'Gia Phú'],
            ],

            // 4. Nam Định
            'Nam Định' => [
                'Thành phố Nam Định' => ['Trường Thi', 'Hạ Long'],
                'Hải Hậu' => ['Hải Trung', 'Hải Phương'],
            ],

            // 5. Thanh Hóa
            'Thanh Hóa' => [
                'Đông Sơn' => ['Đông Nam', 'Đông Yên'],
                'Hà Trung' => ['Hà Bắc', 'Hà Bình'],
            ],
        ];

        foreach ($data as $cityName => $districts) {
            // Insert city
            $cityId = DB::table('locations')->insertGetId([
                'name' => $cityName,
                'type' => 'city',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($districts as $districtName => $wards) {
                // Insert district
                $districtId = DB::table('locations')->insertGetId([
                    'name' => $districtName,
                    'type' => 'district',
                    'parent_id' => $cityId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Insert wards
                foreach ($wards as $wardName) {
                    DB::table('locations')->insert([
                        'name' => $wardName,
                        'type' => 'ward',
                        'parent_id' => $districtId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
