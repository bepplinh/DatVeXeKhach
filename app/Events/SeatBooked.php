<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SeatBooked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * $booked: mảng các block theo trip, ví dụ:
     * [
     *   [
     *     'trip_id'     => 101,
     *     'seat_ids'    => [1,2,3],
     *     'seat_labels' => ['A1','A2','B1'],   // optional
     *     'leg_type'    => 'OUT',              // optional
     *     'booking_leg_id' => 555,             // optional
     *   ],
     *   [
     *     'trip_id'     => 202,
     *     'seat_ids'    => [7,8],
     *     'seat_labels' => ['C1','C2'],
     *     'leg_type'    => 'RETURN',
     *     'booking_leg_id' => 556,
     *   ],
     * ]
     */
    public function __construct(
        public string $sessionToken,
        public int    $bookingId,
        public array  $booked,        // dữ liệu đã book, nhóm theo trip
        public ?int   $userId = null  // optional
    ) {}

    public function broadcastOn(): array
    {
        $tripIds = [];
        foreach ($this->booked as $b) {
            $tripIds[] = (int) ($b['trip_id'] ?? 0);
        }
        $tripIds = array_values(array_unique(array_filter($tripIds)));

        $channels = [];
        foreach ($tripIds as $tripId) {
            $channels[] = new PrivateChannel("trip.{$tripId}");
        }

        // kênh riêng cho client (giống SeatLocked)
        $channels[] = new PrivateChannel("client.session.{$this->sessionToken}");

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'SeatBooked';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id'    => $this->bookingId,
            'user_id'       => $this->userId,
            'session_token' => $this->sessionToken,
            'booked'        => $this->booked,      // danh sách theo trip như đã mô tả
            'at'            => now()->toIso8601String(),
        ];
    }
}
