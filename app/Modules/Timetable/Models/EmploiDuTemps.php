<?php

namespace App\Modules\Timetable\Models;

use App\Models\Filiere;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiDuTemps extends Model
{
    use HasFactory;

    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'filiere_id',
        'division',
        'semestre',
        'date_debut_semaine',
        'date_fin_semaine',
        'observation',
        'contact_responsable_nom',
        'contact_responsable_tel',
    ];

    protected $casts = [
        'date_debut_semaine' => 'date',
        'date_fin_semaine' => 'date',
    ];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }
}