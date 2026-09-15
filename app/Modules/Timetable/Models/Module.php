<?php

namespace App\Modules\Timetable\Models;

use App\Models\Cycle;
use App\Models\Filiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    public const PALETTE_COULEURS = [
        '#3B82F6', // Bleu
        '#10B981', // Vert
        '#F59E0B', // Jaune / Ambre
        '#EF4444', // Rouge
        '#8B5CF6', // Violet
        '#EC4899', // Rose
        '#06B6D4', // Cyan
        '#F97316', // Orange
    ];

    protected $fillable = [
        'filiere_id',
        'cycle_id',
        'intitule',
        'volume_horaire',
        'enseignant_id',
        'couleur',
    ];

    public function getCouleurAttribute(?string $value): string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        $id = $this->id ?? 0;
        $index = abs($id % 8);

        return self::PALETTE_COULEURS[$index];
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(\App\Modules\GradeTracking\Models\Evaluation::class);
    }
}