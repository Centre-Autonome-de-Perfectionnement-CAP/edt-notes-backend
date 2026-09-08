<?php
// app/Modules/GradeTracking/Models/GradeSubmission.php

namespace App\Modules\GradeTracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeSubmission extends Model
{
    use HasFactory;

    protected $table = 'grade_submissions';

    protected $fillable = [
        'evaluation_id',
        'enseignant_id',
        'statut',
        'qr_hash',
        'pdf_path',
        'date_soumission',
        'date_archivage',
        'archive_par',
    ];

    protected $casts = [
        'date_soumission' => 'datetime',
        'date_archivage' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function archivePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par');
    }
}