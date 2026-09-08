<?php
// app/Modules/GradeTracking/Models/Note.php

namespace App\Modules\GradeTracking\Models;

use App\Models\Etudiant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $table = 'notes';

    protected $fillable = [
        'evaluation_id',
        'etudiant_id',
        'valeur',
        'absent',
        'verrouille',
    ];

    protected $casts = [
        'valeur' => 'decimal:2',
        'absent' => 'boolean',
        'verrouille' => 'boolean',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }
}