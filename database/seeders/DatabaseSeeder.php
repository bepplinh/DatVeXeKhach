<?php

namespace Database\Seeders;


use App\Models\User;
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
            FakeUserJwtSeeder::class,
            LocationSeeder::class,
            RouteSeeder::class,
            TripStationSeeder::class,
            TripSeeder::class,
            SeatLayoutTemplateSeeder::class,
            BusSeeder::class,
            SeatSeeder::class,
            ScheduleTemplateTripSeeder::class
        ]);
    }
}
