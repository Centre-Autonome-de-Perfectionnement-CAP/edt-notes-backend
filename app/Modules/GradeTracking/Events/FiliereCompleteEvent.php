<?php
// app/Modules/GradeTracking/Events/FiliereCompleteEvent.php

namespace App\Modules\GradeTracking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FiliereCompleteEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $filiereId)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("filiere.{$this->filiereId}.grades"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'filiere.complete';
    }

    public function broadcastWith(): array
    {
        return [
            'filiere_id' => $this->filiereId,
        ];
    }
}