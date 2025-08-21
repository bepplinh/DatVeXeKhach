<?php

namespace Database\Seeders;


use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\BusSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'username' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('1234'),
        ]);

        $this->call([
            DefaultCouponsSeeder::class,
            BirthdayCouponSeeder::class,
            LocationSeeder::class,
            RouteSeeder::class,
            TripSeeder::class,
            TripStationSeeder::class,
            BusSeeder::class,
            SeatSeeder::class,
            CouponUserSeeder::class,
        ]);
    }
}
