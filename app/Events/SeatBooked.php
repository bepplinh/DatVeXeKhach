<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SeatBooked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $tripId,
        public int $seatId,
        public int $bookingId,
        public int $userId,
        public string $status = 'booked'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * 
     * Channel structure: private.trip.{tripId}
     * This allows:
     * - Trip level: Listen to all seat events in a trip
     * - All seats in a trip are managed through this single channel
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Channel cho toàn bộ trip
            new PrivateChannel("trip.{$this->tripId}"),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string 
    { 
        return 'seat.booked'; 
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'seat_id' => $this->seatId,
            'booking_id' => $this->bookingId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'timestamp' => now()->toISOString(),
        ];
    }
}
