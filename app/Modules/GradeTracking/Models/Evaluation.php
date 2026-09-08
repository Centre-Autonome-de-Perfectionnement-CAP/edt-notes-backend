<?php

namespace App\Modules\GradeTracking\Models;

use App\Modules\Timetable\Models\Module;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evaluation extends Model
{
    use HasFactory;

    protected $table = 'evaluations';

    protected $fillable = [
        'module_id',
        'type',
        'libelle',
        'date_prevue',
    ];

    protected $casts = [
        'date_prevue' => 'date',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function gradeSubmission(): HasOne
    {
        return $this->hasOne(GradeSubmission::class);
    }
}