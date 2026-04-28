<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReverbPing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $message;
    public string $sentAt;

    public function __construct(string $message = 'pong')
    {
        $this->message = $message;
        $this->sentAt = now()->toIso8601String();
    }

    public function broadcastOn(): Channel
    {
        return new Channel('reverb-test');
    }

    public function broadcastAs(): string
    {
        return 'ping';
    }
}
