<?php
// app/Modules/GradeTracking/Events/NoteValideeEvent.php

namespace App\Modules\GradeTracking\Events;

use App\Modules\GradeTracking\Models\GradeSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoteValideeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GradeSubmission $submission)
    {
    }

    /**
     * Canal privé par filière : filiere.{id}.grades
     */
    public function broadcastOn(): array
    {
        $filiereId = $this->submission->evaluation->module->filiere_id;

        return [
            new PrivateChannel("filiere.{$filiereId}.grades"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.validee';
    }

    public function broadcastWith(): array
    {
        return [
            'evaluation_id' => $this->submission->evaluation_id,
            'module_id' => $this->submission->evaluation->module_id,
            'statut' => $this->submission->statut,
        ];
    }
}