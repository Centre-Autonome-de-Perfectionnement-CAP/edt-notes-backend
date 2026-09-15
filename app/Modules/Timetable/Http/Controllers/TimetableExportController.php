<?php

namespace App\Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Timetable\Models\EmploiDuTemps;
use App\Modules\Timetable\Models\Seance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableExportController extends Controller
{
    /**
     * GET /api/v1/timetable/emploi-du-temps/{id}/export
     * Génère le PDF conforme au template officiel de la RdivFC (grille A4 paysage dynamique).
     */
    public function export(EmploiDuTemps $emploiDuTemps): Response
    {
        $emploiDuTemps->load('filiere');

        // Récupération de toutes les séances non annulées pour la semaine
        $seances = Seance::forFiliere($emploiDuTemps->filiere_id)
            ->whereBetween('date', [
                $emploiDuTemps->date_debut_semaine->toDateString(),
                $emploiDuTemps->date_fin_semaine->toDateString(),
            ])
            ->where('statut', '!=', 'annule')
            ->with(['module', 'enseignant'])
            ->orderBy('date')
            ->orderBy('heure_debut')
            ->get();

        // 1. Liste dynamique des jours distincts couverts par les séances de la semaine
        $jours = $seances->pluck('date')
            ->unique(fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->sort()
            ->values()
            ->map(function ($d) {
                $dateObj = $d instanceof Carbon ? $d : Carbon::parse($d);
                return [
                    'date' => $dateObj->toDateString(),
                    'label' => mb_strtoupper($dateObj->locale('fr')->isoFormat('dddd DD/MM/YYYY')),
                    'jour_nom' => mb_strtoupper($dateObj->locale('fr')->isoFormat('dddd')),
                    'jour_date' => $dateObj->format('d/m/Y'),
                ];
            });

        // 2. Liste dynamique des créneaux horaires réellement occupés
        $creneaux = $seances->map(function ($s) {
            $hDebut = substr($s->heure_debut, 0, 5);
            $hFin = substr($s->heure_fin, 0, 5);
            return [
                'key' => "{$hDebut}-{$hFin}",
                'heure_debut' => $hDebut,
                'heure_fin' => $hFin,
                'label' => "{$hDebut} - {$hFin}",
            ];
        })
        ->unique('key')
        ->sortBy('heure_debut')
        ->values();

        // 3. Matrice de la grille : grille[creneau_key][date_string] = Seance|null
        $grille = [];
        foreach ($creneaux as $creneau) {
            $grille[$creneau['key']] = [];
            foreach ($jours as $jour) {
                $matchingSeance = $seances->first(function ($s) use ($jour, $creneau) {
                    $sDate = $s->date instanceof Carbon ? $s->date->toDateString() : (string) $s->date;
                    $sDebut = substr($s->heure_debut, 0, 5);
                    $sFin = substr($s->heure_fin, 0, 5);
                    return $sDate === $jour['date'] && $sDebut === $creneau['heure_debut'] && $sFin === $creneau['heure_fin'];
                });

                $grille[$creneau['key']][$jour['date']] = $matchingSeance;
            }
        }

        // 4. Modules distincts apparaissant dans la semaine pour la légende
        $modules = $seances->pluck('module')->filter()->unique('id')->values();

        $pdf = Pdf::loadView('pdf.emploi-du-temps', [
            'emploiDuTemps' => $emploiDuTemps,
            'seances' => $seances,
            'jours' => $jours,
            'creneaux' => $creneaux,
            'grille' => $grille,
            'modules' => $modules,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            'emploi-du-temps_%s_%s.pdf',
            str($emploiDuTemps->filiere->code ?? $emploiDuTemps->filiere->nom)->slug(),
            $emploiDuTemps->date_debut_semaine->format('Y-m-d')
        );

        return $pdf->stream($filename);
    }

    /**
     * GET /api/v1/timetable/emploi-du-temps/{id}/export-csv
     * Génère un CSV à plat des séances de la semaine.
     */
    public function exportCsv(EmploiDuTemps $emploiDuTemps): StreamedResponse
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
            ->get();

        $filename = sprintf(
            'emploi-du-temps_%s_%s.csv',
            str($emploiDuTemps->filiere->code ?? $emploiDuTemps->filiere->nom)->slug(),
            $emploiDuTemps->date_debut_semaine->format('Y-m-d')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($seances) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // En-têtes de colonnes
            fputcsv($handle, [
                'jour',
                'date',
                'heure_debut',
                'heure_fin',
                'module',
                'salle',
                'enseignant',
                'telephone_enseignant',
            ]);

            foreach ($seances as $seance) {
                $dateObj = $seance->date instanceof Carbon ? $seance->date : Carbon::parse($seance->date);
                fputcsv($handle, [
                    mb_strtoupper($dateObj->locale('fr')->isoFormat('dddd')),
                    $dateObj->format('Y-m-d'),
                    substr($seance->heure_debut, 0, 5),
                    substr($seance->heure_fin, 0, 5),
                    $seance->module->intitule ?? '',
                    $seance->salle ?? '',
                    $seance->enseignant->name ?? '',
                    $seance->enseignant->telephone ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}