<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ContactScoreProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $contactId,
        public string $email,
        public int $score,
        public string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('contacts.' . $this->contactId);
    }

    public function broadcastWith(): array
    {
        return [
            'contact_id' => $this->contactId,
            'email' => $this->email,
            'score' => $this->score,
            'status' => $this->status,
        ];
    }
}
