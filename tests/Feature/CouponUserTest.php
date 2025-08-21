<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Coupon;
use App\Models\CouponUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CouponUserTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $coupon;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo user và coupon test
        $this->user = User::factory()->create();
        $this->coupon = Coupon::create([
            'code' => 'TEST123',
            'name' => 'Test Coupon',
            'description' => 'Test Description',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 100000,
            'max_discount_amount' => 50000,
            'usage_limit' => 100,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_list_coupon_users()
    {
        // Tạo một số coupon-user
        CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->getJson('/api/coupon-users');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'user_id',
                                'coupon_id',
                                'is_used',
                                'used_at',
                                'created_at',
                                'updated_at',
                                'user',
                                'coupon'
                            ]
                        ]
                    ],
                    'message'
                ]);
    }

    /** @test */
    public function it_can_create_coupon_user()
    {
        $data = [
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ];

        $response = $this->postJson('/api/coupon-users', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'user_id',
                        'coupon_id',
                        'is_used',
                        'used_at',
                        'created_at',
                        'updated_at'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('coupon_user', $data);
    }

    /** @test */
    public function it_can_show_coupon_user()
    {
        $couponUser = CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->getJson("/api/coupon-users/{$couponUser->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'user_id',
                        'coupon_id',
                        'is_used',
                        'used_at',
                        'created_at',
                        'updated_at'
                    ],
                    'message'
                ]);
    }

    /** @test */
    public function it_can_update_coupon_user()
    {
        $couponUser = CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $updateData = [
            'is_used' => true,
            'used_at' => now(),
        ];

        $response = $this->putJson("/api/coupon-users/{$couponUser->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message'
                ]);

        $this->assertDatabaseHas('coupon_user', [
            'id' => $couponUser->id,
            'is_used' => true,
        ]);
    }

    /** @test */
    public function it_can_delete_coupon_user()
    {
        $couponUser = CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->deleteJson("/api/coupon-users/{$couponUser->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);

        $this->assertDatabaseMissing('coupon_user', ['id' => $couponUser->id]);
    }

    /** @test */
    public function it_can_get_coupon_users_by_user_id()
    {
        CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->getJson("/api/coupon-users/user/{$this->user->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_get_coupon_users_by_coupon_id()
    {
        CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->getJson("/api/coupon-users/coupon/{$this->coupon->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_mark_coupon_as_used()
    {
        $couponUser = CouponUser::create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'is_used' => false,
        ]);

        $response = $this->postJson("/api/coupon-users/{$couponUser->id}/use");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message'
                ]);

        $this->assertDatabaseHas('coupon_user', [
            'id' => $couponUser->id,
            'is_used' => true,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        $response = $this->postJson('/api/coupon-users', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id', 'coupon_id']);
    }

    /** @test */
    public function it_validates_user_exists_on_create()
    {
        $data = [
            'user_id' => 99999,
            'coupon_id' => $this->coupon->id,
        ];

        $response = $this->postJson('/api/coupon-users', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function it_validates_coupon_exists_on_create()
    {
        $data = [
            'user_id' => $this->user->id,
            'coupon_id' => 99999,
        ];

        $response = $this->postJson('/api/coupon-users', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['coupon_id']);
    }
}
