<?php

namespace App\Modules\Timetable\Events;

use App\Modules\Timetable\Models\Seance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeanceProgrammeeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Seance $seance,
        public string $action = 'created', // created | updated | cancelled
    ) {}

    /**
     * Canal privé par filière : private-filiere.{id}.timetable
     */
    public function broadcastOn(): array
    {
        $filiereId = $this->seance->module->filiere_id;

        return [
            new PrivateChannel("filiere.{$filiereId}.timetable"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'seance.programmee';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'seance' => $this->seance->load(['module', 'enseignant']),
        ];
    }
}