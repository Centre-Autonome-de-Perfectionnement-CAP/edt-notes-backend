<?php
// app/Modules/GradeTracking/Http/Controllers/GradeTrackingController.php

namespace App\Modules\GradeTracking\Http\Controllers;

use App\Models\Etudiant;
use App\Modules\GradeTracking\Http\Requests\SubmitNotesRequest;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\GradeTracking\Models\Note;
use App\Modules\GradeTracking\Services\QrCodeService;
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
     * Liste des étudiants de la filière concernée par cette évaluation,
     * utilisée côté mobile pour le matching OCR avant soumission.
     */
    public function roster(Evaluation $evaluation): JsonResponse
    {
        $etudiants = Etudiant::where('filiere_id', $evaluation->module->filiere_id)
            ->orderBy('nom')
            ->get(['id', 'matricule', 'nom', 'prenoms']);

        return response()->json(['data' => $etudiants]);
    }

    /**
     * Soumission groupée des notes d'une évaluation. Verrouille, applique
     * la règle "absent par défaut", et crée la soumission (sans PDF/QR
     * pour l'instant — étapes suivantes).
     */
    public function submitNotes(SubmitNotesRequest $request, Evaluation $evaluation): JsonResponse
    {
        if ($evaluation->module->enseignant_id !== $request->user()->id) {
            return response()->json([
                'message' => "Vous n'êtes pas l'enseignant de ce module.",
            ], 403);
        }

        if ($evaluation->gradeSubmission()->exists()) {
            return response()->json([
                'message' => 'Les notes de cette évaluation sont déjà verrouillées.',
            ], 409);
        }

        $rosterEtudiants = Etudiant::where('filiere_id', $evaluation->module->filiere_id)->get();
        $notesEnvoyees = collect($request->validated('notes'))->keyBy('etudiant_id');

        $submission = DB::transaction(function () use ($evaluation, $request, $rosterEtudiants, $notesEnvoyees) {
            $submission = GradeSubmission::create([
                'evaluation_id' => $evaluation->id,
                'enseignant_id' => $request->user()->id,
                'statut' => 'soumis',
                'qr_hash' => '', // calculé juste après, une fois les notes créées
                'date_soumission' => now(),
            ]);

            foreach ($rosterEtudiants as $etudiant) {
                $donnee = $notesEnvoyees->get($etudiant->id);

                Note::create([
                    'evaluation_id' => $evaluation->id,
                    'etudiant_id' => $etudiant->id,
                    'valeur' => $donnee['valeur'] ?? null,
                    // Règle métier : étudiant omis du payload = absent par défaut
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

        // TODO (étape suivante) : génération du PDF récapitulatif, remplir submission->pdf_path

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
        return response()->json(['message' => 'Cette soumission est déjà archivée.'], 409);
    }

    $notes = $submission->evaluation->notes;

    if (! $this->qrCodeService->verify($submission, $notes, $request->validated('hash'))) {
        return response()->json(['message' => 'QR code invalide ou falsifié.'], 422);
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
            $filiereAffichage = $cycleLibelle ? "{$filiereNom} — {$cycleLibelle}" : $filiereNom;

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
                    
                    $libelle = $libelles[$evaluation->type] ?? ucfirst($evaluation->type);

                    $hasSubmission = $evaluation->gradeSubmission !== null;

                    return [
                        'id' => $evaluation->id,
                        'libelle' => $libelle,
                        'statut' => $hasSubmission ? 'verrouille' : 'non_commence',
                        'submission_id' => $hasSubmission ? $evaluation->gradeSubmission->id : null,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json(['data' => $data]);
    }

}