<?php

namespace App\Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Timetable\Models\EmploiDuTemps;
use App\Modules\Timetable\Models\Seance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TimetableExportController extends Controller
{
    /**
     * GET /api/v1/timetable/emploi-du-temps/{id}/export
     * Génère le PDF conforme au template officiel de la RdivFC.
     */
    public function export(EmploiDuTemps $emploiDuTemps): Response
    {
        $emploiDuTemps->load('filiere');

        $seances = Seance::forFiliere($emploiDuTemps->filiere_id)
            ->whereBetween('date', [
                $emploiDuTemps->date_debut_semaine->toDateString(),
                $emploiDuTemps->date_fin_semaine->toDateString(),
            ])
            ->where('statut', '!=', 'annule')
            ->with(['module', 'enseignant'])
            ->orderBy('date')
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn ($seance) => $seance->date->translatedFormat('l d/m'));

        $pdf = Pdf::loadView('pdf.emploi-du-temps', [
            'emploiDuTemps' => $emploiDuTemps,
            'seancesParJour' => $seances,
        ]);

        $filename = sprintf(
            'emploi-du-temps_%s_%s.pdf',
            str($emploiDuTemps->filiere->code ?? $emploiDuTemps->filiere->nom)->slug(),
            $emploiDuTemps->date_debut_semaine->format('Y-m-d')
        );

        return $pdf->stream($filename);
    }
}