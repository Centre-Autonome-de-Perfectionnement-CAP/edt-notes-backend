<?php

namespace App\Modules\Timetable\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seance extends Model
{
    use HasFactory;

    protected $table = 'seances';

    protected $fillable = [
        'module_id',
        'enseignant_id',
        'date',
        'heure_debut',
        'heure_fin',
        'salle',
        'type',
        'statut',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * Scope : séances d'une filière donnée (via le module rattaché).
     */
    public function scopeForFiliere(Builder $query, int $filiereId): Builder
    {
        return $query->whereHas('module', function (Builder $q) use ($filiereId) {
            $q->where('filiere_id', $filiereId);
        });
    }

    /**
     * Scope : séances d'un enseignant donné.
     */
    public function scopeForEnseignant(Builder $query, int $enseignantId): Builder
    {
        return $query->where('enseignant_id', $enseignantId);
    }

    /**
     * Scope : séances à venir (à partir d'aujourd'hui), non annulées.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date', '>=', now()->toDateString())
            ->where('statut', '!=', 'annule')
            ->orderBy('date')
            ->orderBy('heure_debut');
    }
}