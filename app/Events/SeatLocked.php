<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SeatLocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionToken,
        public array $locks
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        foreach ($this->locks as $lock) {
            $tripId = (int) $lock['trip_id'];
            $channels[] = new PrivateChannel("trip.{$tripId}");
        }

        // Nếu bạn muốn kênh dành riêng cho client (tuỳ chọn)
        $channels[] = new PrivateChannel("client.session.{$this->sessionToken}");

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'SeatLocked';
    }

    public function broadcastWith(): array
    {
        return [
            'session_token' => $this->sessionToken,
            'locks'         => $this->locks,
        ];
    }
}
