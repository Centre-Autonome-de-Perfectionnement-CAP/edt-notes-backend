<?php
// app/Modules/GradeTracking/Http/Controllers/GradeTrackingController.php

namespace App\Modules\GradeTracking\Http\Controllers;

use App\Models\Etudiant;
use App\Models\Setting;
use App\Models\Filiere;
use App\Modules\GradeTracking\Http\Requests\SubmitNotesRequest;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\GradeTracking\Models\Note;
use App\Modules\GradeTracking\Services\QrCodeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use App\Modules\GradeTracking\Services\GradeTrackingService;
use App\Modules\GradeTracking\Events\{NoteValideeEvent, FiliereCompleteEvent};
use Illuminate\Support\Facades\Storage;
use App\Modules\GradeTracking\Http\Requests\ArchiveSubmissionRequest;
use App\Modules\GradeTracking\Services\GradeRecapPdfService;
use App\Modules\Timetable\Models\Module;
use Illuminate\Http\Request;

class GradeTrackingController extends Controller
{
    public function __construct(
    private QrCodeService $qrCodeService,
    private GradeTrackingService $gradeTrackingService,   
    private GradeRecapPdfService $pdfService, 
) {}

    /**
     * Liste des Ã©tudiants de la filiÃ¨re concernÃ©e par cette Ã©valuation,
     * utilisÃ©e cÃ´tÃ© mobile pour le matching OCR avant soumission.
     */
    private function getRosterForEvaluation(Evaluation $evaluation)
    {
        $etudiants = Etudiant::where('filiere_id', $evaluation->module->filiere_id)->orderBy('nom')->get();

        if (strtolower($evaluation->type) === 'rattrapage') {
            $devoirs = Evaluation::where('module_id', $evaluation->module_id)
                ->where('id', '!=', $evaluation->id)
                ->where('type', 'devoir')
                ->with('notes')
                ->get();
            
            if ($devoirs->isEmpty()) {
                return collect([]);
            }

            $seuil = (float) (\App\Models\Setting::where('key', 'seuil_validation')->value('value') ?? 10);
            $etudiantsEnRattrapage = collect();

            foreach ($etudiants as $etudiant) {
                $total = 0;
                $count = 0;
                foreach ($devoirs as $devoir) {
                    $note = $devoir->notes->firstWhere('etudiant_id', $etudiant->id);
                    if ($note && !$note->absent && $note->valeur !== null) {
                        $total += $note->valeur;
                        $count++;
                    } elseif ($note && $note->absent) {
                        $total += 0;
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    $moyenne = $total / $count;
                    if ($moyenne < $seuil) {
                        $etudiantsEnRattrapage->push($etudiant);
                    }
                }
            }
            return $etudiantsEnRattrapage;
        }

        return $etudiants;
    }

    public function roster(Evaluation $evaluation): JsonResponse
    {
        $etudiants = $this->getRosterForEvaluation($evaluation);
        return response()->json(['data' => $etudiants->map->only(['id', 'matricule', 'nom', 'prenoms'])->values()]);
    }

    /**
     * Soumission groupÃ©e des notes d'une Ã©valuation. Verrouille, applique
     * la rÃ¨gle "absent par dÃ©faut", et crÃ©e la soumission (sans PDF/QR
     * pour l'instant â€” Ã©tapes suivantes).
     */
    public function submitNotes(SubmitNotesRequest $request, Evaluation $evaluation): JsonResponse
    {
        if ($evaluation->module->enseignant_id !== $request->user()->id) {
            return response()->json([
                'message' => "Vous n'Ãªtes pas l'enseignant de ce module.",
            ], 403);
        }

        if ($evaluation->gradeSubmission()->exists()) {
            return response()->json([
                'message' => 'Les notes de cette Ã©valuation sont dÃ©jÃ  verrouillÃ©es.',
            ], 409);
        }

        $rosterEtudiants = $this->getRosterForEvaluation($evaluation);
        $notesEnvoyees = collect($request->validated('notes'))->keyBy('etudiant_id');

        $submission = DB::transaction(function () use ($evaluation, $request, $rosterEtudiants, $notesEnvoyees) {
            $submission = GradeSubmission::create([
                'evaluation_id' => $evaluation->id,
                'enseignant_id' => $request->user()->id,
                'statut' => 'soumis',
                'qr_hash' => '', // calculÃ© juste aprÃ¨s, une fois les notes crÃ©Ã©es
                'date_soumission' => now(),
            ]);

            foreach ($rosterEtudiants as $etudiant) {
                $donnee = $notesEnvoyees->get($etudiant->id);

                Note::create([
                    'evaluation_id' => $evaluation->id,
                    'etudiant_id' => $etudiant->id,
                    'valeur' => $donnee['valeur'] ?? null,
                    // RÃ¨gle mÃ©tier : Ã©tudiant omis du payload = absent par dÃ©faut
                    'absent' => $donnee ? ($donnee['absent'] ?? false) : true,
                    'verrouille' => true,
                ]);
            }

            $hash = $this->qrCodeService->computeHash($submission, $evaluation->notes()->get());
            $submission->update(['qr_hash' => $hash]);

            $pdfPath = $this->pdfService->generate($submission);
            $submission->update(['pdf_path' => $pdfPath]);

            return $submission;
        });

        event(new NoteValideeEvent($submission));

        $filiereId = $evaluation->module->filiere_id;
        if ($this->gradeTrackingService->isFiliereComplete($filiereId)) {
            event(new FiliereCompleteEvent($filiereId));
        }

        // TODO (Ã©tape suivante) : gÃ©nÃ©ration du PDF rÃ©capitulatif, remplir submission->pdf_path

        return response()->json($submission->fresh(), 201);
    }

    public function filiereStatus(int $filiere): JsonResponse
{
    return response()->json($this->gradeTrackingService->computeFiliereStatus($filiere));
}

public function dashboard(): JsonResponse
{
    return response()->json(['data' => $this->gradeTrackingService->dashboardData()]);
}

public function downloadPdf(GradeSubmission $submission)
{
    abort_unless(
        $submission->pdf_path && Storage::disk('local')->exists($submission->pdf_path),
        404
    );

    return Storage::disk('local')->download(
        $submission->pdf_path,
        "recap-notes-{$submission->id}.pdf"
    );
}

public function archive(ArchiveSubmissionRequest $request, GradeSubmission $submission): JsonResponse
{
    if ($submission->statut === 'archive') {
        return response()->json(['message' => 'Cette soumission est dÃ©jÃ  archivÃ©e.'], 409);
    }

    $notes = $submission->evaluation->notes;

    if (! $this->qrCodeService->verify($submission, $notes, $request->validated('hash'))) {
        return response()->json(['message' => 'QR code invalide ou falsifiÃ©.'], 422);
    }

    $submission->update([
        'statut' => 'archive',
        'date_archivage' => now(),
        'archive_par' => $request->user()->id,
    ]);

    return response()->json($submission->fresh());
}

    public function mesModules(Request $request): JsonResponse
    {
        $modules = Module::where('enseignant_id', $request->user()->id)
            ->with(['filiere', 'cycle', 'evaluations.gradeSubmission'])
            ->get();

        $data = $modules->map(function ($module) {
            $filiereNom = $module->filiere->nom ?? '';
            $cycleLibelle = $module->cycle->libelle ?? '';
            $filiereAffichage = $cycleLibelle ? "{$filiereNom} â€” {$cycleLibelle}" : $filiereNom;

            return [
                'id' => $module->id,
                'nom' => $module->intitule,
                'filiere' => $filiereAffichage,
                'evaluations' => $module->evaluations->map(function ($evaluation) {
                    $libelles = [
                        'devoir1' => 'Devoir 1',
                        'devoir2' => 'Devoir 2',
                        'examen' => 'Examen',
                        'rattrapage' => 'Rattrapage',
                    ];
                    
                    $libelle = $evaluation->titre ?: ($libelles[$evaluation->type] ?? ucfirst($evaluation->type));

                    $hasSubmission = $evaluation->gradeSubmission !== null;

                    return [
                        'id' => $evaluation->id,
                        'type' => $evaluation->type,
                        'titre' => $evaluation->titre,
                        'libelle' => $libelle,
                        'statut' => $hasSubmission ? 'verrouille' : 'non_commence',
                        'submission_id' => $hasSubmission ? $evaluation->gradeSubmission->id : null,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/grade-tracking/delegue/notes
     * Permet au responsable de classe (dÃ©lÃ©guÃ©) de consulter les notes des matiÃ¨res
     * de sa filiÃ¨re avec application du dÃ©lai de rÃ©tention de 2 jours (48h) post-dÃ©pÃ´t.
     */
    public function delegueNotes(Request $request): JsonResponse
    {
        $user = $request->user();
        $filiereId = $user->filiere_id ?? $request->query('filiere_id');

        if (! $filiereId) {
            return response()->json([
                'message' => 'Aucune filiÃ¨re associÃ©e Ã  ce compte responsable de classe.',
            ], 400);
        }

        $filiere = Filiere::find($filiereId);
        if (! $filiere) {
            return response()->json(['message' => 'FiliÃ¨re introuvable.'], 404);
        }

        $modules = Module::where('filiere_id', $filiereId)
            ->with([
                'enseignant',
                'evaluations.gradeSubmission',
                'evaluations.notes.etudiant',
            ])
            ->get();

        $modulesData = $modules->map(function ($module) {
            return [
                'id' => $module->id,
                'intitule' => $module->intitule,
                'volume_horaire' => $module->volume_horaire,
                'enseignant' => $module->enseignant ? [
                    'id' => $module->enseignant->id,
                    'nom' => $module->enseignant->name,
                    'telephone' => $module->enseignant->telephone,
                ] : null,
                'evaluations' => $module->evaluations->map(function ($evaluation) {
                    $submission = $evaluation->gradeSubmission;
                    $libelles = [
                        'devoir1' => 'Devoir 1',
                        'devoir2' => 'Devoir 2',
                        'examen' => 'Examen',
                        'rattrapage' => 'Rattrapage',
                    ];
                    $libelle = $evaluation->titre ?: ($libelles[$evaluation->type] ?? ucfirst($evaluation->type));

                    if (! $submission) {
                        return [
                            'id' => $evaluation->id,
                            'type' => $evaluation->type,
                            'libelle' => $libelle,
                            'statut' => 'non_soumis',
                            'libelle_statut' => 'Notes non encore renseignÃ©es par l\'enseignant',
                            'delai_ecoule' => false,
                            'date_soumission' => null,
                            'date_ouverture' => null,
                            'heures_restantes' => null,
                            'notes' => [],
                            'statistiques' => null,
                        ];
                    }

                    $dateSoumission = Carbon::parse($submission->date_soumission);
                    $dateOuverture = $dateSoumission->copy()->addDays(2);
                    $delaiEcoule = now()->greaterThanOrEqualTo($dateOuverture);

                    if (! $delaiEcoule) {
                        $heuresRestantes = max(1, (int) ceil(now()->diffInHours($dateOuverture, false)));
                        return [
                            'id' => $evaluation->id,
                            'type' => $evaluation->type,
                            'libelle' => $libelle,
                            'statut' => 'en_attente_delai',
                            'libelle_statut' => 'En attente du dÃ©lai de 2 jours post-dÃ©pÃ´t',
                            'delai_ecoule' => false,
                            'date_soumission' => $dateSoumission->toIso8601String(),
                            'date_ouverture' => $dateOuverture->toIso8601String(),
                            'heures_restantes' => $heuresRestantes,
                            'notes' => [],
                            'statistiques' => null,
                        ];
                    }

                    $notesCollection = $evaluation->notes->map(function ($note) {
                        return [
                            'etudiant_id' => $note->etudiant_id,
                            'matricule' => $note->etudiant->matricule ?? '',
                            'nom_complet' => trim(($note->etudiant->nom ?? '') . ' ' . ($note->etudiant->prenoms ?? '')),
                            'valeur' => $note->valeur !== null ? (float) $note->valeur : null,
                            'absent' => (bool) $note->absent,
                        ];
                    })->sortBy('nom_complet')->values();

                    $notesPresentes = $notesCollection->where('absent', false)->pluck('valeur')->filter(fn ($v) => $v !== null);

                    $stats = [
                        'total_etudiants' => $notesCollection->count(),
                        'total_presents' => $notesPresentes->count(),
                        'total_absents' => $notesCollection->where('absent', true)->count(),
                        'moyenne' => $notesPresentes->count() > 0 ? round($notesPresentes->avg(), 2) : null,
                        'note_min' => $notesPresentes->count() > 0 ? (float) $notesPresentes->min() : null,
                        'note_max' => $notesPresentes->count() > 0 ? (float) $notesPresentes->max() : null,
                    ];

                    return [
                        'id' => $evaluation->id,
                        'type' => $evaluation->type,
                        'libelle' => $libelle,
                        'statut' => 'disponible',
                        'libelle_statut' => 'Notes publiÃ©es et consultables',
                        'delai_ecoule' => true,
                        'date_soumission' => $dateSoumission->toIso8601String(),
                        'date_ouverture' => $dateOuverture->toIso8601String(),
                        'heures_restantes' => 0,
                        'statistiques' => $stats,
                        'notes' => $notesCollection->all(),
                    ];
                })->values()->all(),
            ];
        });

        return response()->json([
            'filiere' => [
                'id' => $filiere->id,
                'nom' => $filiere->nom,
                'code' => $filiere->code,
            ],
            'data' => $modulesData,
        ]);
    }

    /**
     * POST /api/v1/grade-tracking/modules/{module}/evaluations
     * Permet Ã  un enseignant de crÃ©er une nouvelle Ã©valuation pour son module.
     */
    public function storeEvaluation(Request $request, Module $module): JsonResponse
    {
        if ($module->enseignant_id !== $request->user()->id && $request->user()->role !== 'responsable_pedagogique') {
            return response()->json(['message' => 'Non autorisÃ©.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|string',
            'libelle' => 'required|string|max:255',
            'date_prevue' => 'nullable|date',
        ]);

        $evaluation = \App\Modules\GradeTracking\Models\Evaluation::create([
            'module_id' => $module->id,
            'type' => $validated['type'],
            'libelle' => $validated['libelle'],
            'date_prevue' => $validated['date_prevue'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Ã‰valuation crÃ©Ã©e avec succÃ¨s',
            'data' => $evaluation,
        ], 201);
    }
    /**
     * DELETE /api/v1/grade-tracking/evaluations/{evaluation}
     * Permet à un enseignant de supprimer une évaluation non verrouillée.
     */
    public function destroyEvaluation(Request $request, Evaluation $evaluation): JsonResponse
    {
        if ($evaluation->module->enseignant_id !== $request->user()->id && $request->user()->role !== 'responsable_pedagogique') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($evaluation->statut === 'verrouille' || $evaluation->gradeSubmission()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer une évaluation avec des notes soumises ou verrouillée.'], 409);
        }

        $evaluation->delete();

        return response()->json(['message' => 'Évaluation supprimée avec succès']);
    }
}
