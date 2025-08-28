<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Trip;
use App\Models\Seat;
use App\Models\Bus;
use App\Models\BusType;
use App\Models\Route;
use App\Models\Location;
use App\Models\TripSeatStatus;
use App\Services\SeatSelectionService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SeatSelectionFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $user2;
    protected Trip $trip;
    protected array $seats;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo dữ liệu test
        $this->user = User::factory()->create();
        $this->user2 = User::factory()->create();
        
        $busType = BusType::create(['name' => 'Test Bus Type']);
        $bus = Bus::create([
            'bus_type_id' => $busType->id,
            'plate_number' => 'TEST123',
            'capacity' => 50
        ]);
        
        $origin = Location::create(['name' => 'Origin City', 'type' => 'city']);
        $destination = Location::create(['name' => 'Destination City', 'type' => 'city']);
        
        $route = Route::create([
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
            'distance' => 100
        ]);
        
        $this->trip = Trip::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'departure_time' => now()->addHours(2),
            'arrival_time' => now()->addHours(5),
            'price' => 100000
        ]);
        
        // Tạo 5 ghế test
        $this->seats = [];
        for ($i = 1; $i <= 5; $i++) {
            $this->seats[] = Seat::create([
                'bus_id' => $bus->id,
                'seat_number' => $i,
                'deck' => 'lower',
                'column_group' => 'A',
                'index_in_column' => $i,
                'active' => true
            ]);
        }
    }

    /** @test */
    public function user_can_select_multiple_seats()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Chọn ghế thành công'
            ])
            ->assertJsonStructure([
                'data' => [
                    'selected_seats',
                    'failed_seats',
                    'lock_duration'
                ],
                'session_token'
            ]);

        // Kiểm tra database
        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'locked_by' => $this->user->id
        ]);

        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[1]->id,
            'locked_by' => $this->user->id
        ]);
    }

    /** @test */
    public function multiple_users_can_select_same_seat()
    {
        // User 1 chọn ghế
        $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        // User 2 cũng có thể chọn cùng ghế đó
        $response = $this->actingAs($this->user2)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Chọn ghế thành công'
            ]);

        // Kiểm tra database - cả 2 user đều có thể chọn cùng ghế
        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'locked_by' => $this->user2->id
        ]);
    }

    /** @test */
    public function user_can_book_seats_without_selection()
    {
        // Đặt ghế trực tiếp mà không cần chọn trước
        $response = $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/bookings", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Đặt ghế thành công'
            ])
            ->assertJsonStructure([
                'data' => [
                    'codes',
                    'trip_id'
                ]
            ]);

        // Kiểm tra database
        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'is_booked' => true,
            'booked_by' => $this->user->id,
            'locked_by' => null
        ]);

        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[1]->id,
            'is_booked' => true,
            'booked_by' => $this->user->id,
            'locked_by' => null
        ]);
    }

    /** @test */
    public function first_user_to_book_gets_the_seat()
    {
        // User 1 đặt ghế trước
        $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/bookings", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        // User 2 thử đặt cùng ghế
        $response = $this->actingAs($this->user2)
            ->postJson("/api/trips/{$this->trip->id}/bookings", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Một hoặc nhiều ghế đã có người khác đặt trước. Vui lòng chọn ghế khác.'
            ]);

        // Kiểm tra database - chỉ user 1 có ghế
        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'is_booked' => true,
            'booked_by' => $this->user->id
        ]);

        $this->assertDatabaseMissing('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'booked_by' => $this->user2->id
        ]);
    }

    /** @test */
    public function user_can_unselect_seats()
    {
        // Chọn ghế trước
        $selectResponse = $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id]
            ]);

        $sessionToken = $selectResponse->json('session_token');

        // Hủy chọn ghế
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Session-Token' => $sessionToken])
            ->postJson("/api/trips/{$this->trip->id}/seats/unselect", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hủy chọn ghế thành công'
            ]);

        // Kiểm tra database - ghế đầu tiên không còn bị khóa
        $this->assertDatabaseMissing('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[0]->id,
            'locked_by' => $this->user->id
        ]);

        // Ghế thứ hai vẫn bị khóa
        $this->assertDatabaseHas('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'seat_id' => $this->seats[1]->id,
            'locked_by' => $this->user->id
        ]);
    }

    /** @test */
    public function user_can_unselect_all_seats()
    {
        // Chọn ghế trước
        $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id]
            ]);

        // Hủy tất cả ghế
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/trips/{$this->trip->id}/seats/unselect-all");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hủy tất cả ghế đang chọn thành công'
            ]);

        // Kiểm tra database - không còn ghế nào bị khóa
        $this->assertDatabaseMissing('trip_seat_statuses', [
            'trip_id' => $this->trip->id,
            'locked_by' => $this->user->id
        ]);
    }

    /** @test */
    public function user_can_get_seat_selections()
    {
        // Chọn ghế trước
        $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id]
            ]);

        // Lấy danh sách ghế đang chọn
        $response = $this->actingAs($this->user)
            ->getJson("/api/trips/{$this->trip->id}/seats/selections");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'seat_ids',
                    'session_token',
                    'selected_at'
                ]
            ]);

        $this->assertCount(2, $response->json('data.seat_ids'));
    }

    /** @test */
    public function user_can_check_seat_status()
    {
        // Chọn ghế trước
        $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/select", [
                'seat_ids' => [$this->seats[0]->id]
            ]);

        // Kiểm tra trạng thái ghế
        $response = $this->actingAs($this->user)
            ->postJson("/api/trips/{$this->trip->id}/seats/check-status", [
                'seat_ids' => [$this->seats[0]->id, $this->seats[1]->id, $this->seats[2]->id]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'available_seats',
                    'unavailable_seats'
                ]
            ]);

        $data = $response->json('data');
        $this->assertContains($this->seats[1]->id, $data['available_seats']);
        $this->assertContains($this->seats[2]->id, $data['available_seats']);
        $this->assertContains($this->seats[0]->id, $data['unavailable_seats']);
    }
}
