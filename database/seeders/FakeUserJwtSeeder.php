<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;



class FakeUserJwtSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo admin
        $admin = User::create([
            'name' => 'Test Admin',
            'username' => 'admin tester',
            'email' => null,
            'phone' => null,
            'password' => bcrypt('admin123'),
            'role' => 'admin', // nếu có cột role
        ]);

        // Tạo user thường
        $user = User::create([
            'name' => 'Test User',
            'username' => 'user tester',
            'email' => null,
            'phone' => null,
            'password' => bcrypt('user123'),
            'role' => 'customer', // nếu có cột role
        ]);

        // Sinh JWT token
        $adminToken = JWTAuth::fromUser($admin);
        $userToken  = JWTAuth::fromUser($user);

        // Lưu ra file
        $payload = [
            'note' => 'Dùng Authorization: Bearer <token> để test API',
            'admin' => [
                'name' => $admin->name,
                'password' => 'admin123',
                'token' => $adminToken,
            ],
            'user' => [
                'name' => $user->name,
                'password' => 'user123',
                'token' => $userToken,
            ],
        ];

        Storage::put('jwt_test_users.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
