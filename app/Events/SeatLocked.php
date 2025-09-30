<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatLocked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $tripId, 
        public array $seatIds, 
        public string $byToken, 
        public int $ttl,
        public int $userId 
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("trip.$this->tripId"),
        ];
    }

    public function broadcastWith()
    {
        return [
            'trip_id' => $this->tripId,
            'seat_ids' => $this->seatIds,
            'ttl'      => $this->ttl,
            'user_id'  => $this->userId,
            'ts'       => now()->toISOString(),
        ];
    }
}
