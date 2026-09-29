<?php

namespace App\Modules\GradeTracking\Http\Controllers;

use App\Models\Etudiant;
use App\Models\Setting;
use App\Models\Filiere;
use App\Modules\GradeTracking\Http\Requests\SubmitNotesRequest;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\GradeTracking\Models\Note;
use App\Modules\GradeTracking\Services\GradePdfService;
use App\Modules\GradeTracking\Services\QrCodeService;
use App\Modules\Timetable\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GradeTrackingController extends Controller
{
    public function __construct(
        private readonly GradePdfService ,
        private readonly QrCodeService ,
    ) {}

    private function getRosterForEvaluation(Evaluation $evaluation)
    {
        $etudiants = Etudiant::where('filiere_id', $evaluation->module->filiere_id)->orderBy('nom')->get();

        if (strtolower($evaluation->type) === 'rattrapage') {
            // Find all 'devoir' evaluations for this module that have notes
            $devoirs = Evaluation::where('module_id', $evaluation->module_id)
                ->where('id', '!=', $evaluation->id)
                ->where('type', 'devoir')
                ->with('notes')
                ->get();
            
            if ($devoirs->isEmpty()) {
                // If no devoirs, everyone passes (or no one is in rattrapage). Let's return empty or all?
                // Returning empty is safer.
                return collect([]);
            }

            $seuil = (float) (Setting::where('key', 'seuil_validation')->value('value') ?? 10);
            
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
                
                $moyenne = $count > 0 ? $total / $count : 0;
                if ($moyenne < $seuil) {
                    $etudiantsEnRattrapage->push($etudiant);
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

    public function submitNotes(SubmitNotesRequest $request, Evaluation $evaluation): JsonResponse
    {
        if ($evaluation->module->enseignant_id !== $request->user()->id) {
            return response()->json(['message' => "Vous n'êtes pas l'enseignant de ce module."], 403);
        }

        if ($evaluation->statut === 'verrouille' || $evaluation->gradeSubmission()->exists()) {
            return response()->json(['message' => "Les notes ont déjà été soumises ou l'évaluation est verrouillée."], 409);
        }

        $rosterEtudiants = $this->getRosterForEvaluation($evaluation);
        $notesEnvoyees = collect($request->validated('notes'))->keyBy('etudiant_id');

        $submission = DB::transaction(function () use ($evaluation, $request, $rosterEtudiants, $notesEnvoyees) {
            $submission = GradeSubmission::create([
                'evaluation_id' => $evaluation->id,
                'enseignant_id' => $request->user()->id,
                'statut' => 'soumis',
                'qr_hash' => '',
                'date_soumission' => now(),
            ]);

            foreach ($rosterEtudiants as $etudiant) {
                $donnee = $notesEnvoyees->get($etudiant->id);
                Note::create([
                    'evaluation_id' => $evaluation->id,
                    'etudiant_id' => $etudiant->id,
                    'valeur' => $donnee['valeur'] ?? null,
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

        return response()->json([
            'message' => 'Notes soumises avec succès',
            'data' => [
                'submission_id' => $submission->id,
                'qr_hash' => $submission->qr_hash,
            ]
        ], 201);
    }
