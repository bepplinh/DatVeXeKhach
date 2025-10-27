<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatUnlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionToken,
        public array $unlocks
    ) {}

    public function broadcastOn()
    {
        $channels = [];

        foreach ($this->unlocks as $unlock) {
            $tripId = (int) $unlock['trip_id'];
            $channels[] = new PrivateChannel("trip.{$tripId}");
        }

        if (!empty($this->sessionToken)) {
            $channels[] = new PrivateChannel("client.session.{$this->sessionToken}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'SeatUnlocked';
    }

    public function broadcastWith()
    {
        return [
            'session_token' => $this->sessionToken,
            'unlocks' => $this->unlocks,
        ];
    }
}
