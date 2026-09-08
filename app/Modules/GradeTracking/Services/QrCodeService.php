<?php
// app/Modules/GradeTracking/Services/QrCodeService.php

namespace App\Modules\GradeTracking\Services;

use App\Modules\GradeTracking\Models\GradeSubmission;
use Illuminate\Support\Collection;

class QrCodeService
{
    /**
     * Calcule le hash HMAC-SHA256 à partir des données figées d'une soumission.
     * $notes doit être la collection des Note déjà verrouillées pour cette évaluation.
     */
    public function computeHash(GradeSubmission $submission, Collection $notes): string
    {
        $canonical = $this->buildCanonicalString($submission, $notes);

        return hash_hmac('sha256', $canonical, config('services.qr.secret'));
    }

    /**
     * Vérifie qu'un hash scanné correspond bien aux données actuellement stockées.
     */
    public function verify(GradeSubmission $submission, Collection $notes, string $hashScanne): bool
    {
        $hashAttendu = $this->computeHash($submission, $notes);

        return hash_equals($hashAttendu, $hashScanne);
    }

    private function buildCanonicalString(GradeSubmission $submission, Collection $notes): string
    {
        $lignesNotes = $notes
            ->sortBy('etudiant_id')
            ->map(fn ($note) => $note->etudiant_id.':'.($note->absent ? 'ABS' : $note->valeur))
            ->implode(',');

        return implode('|', [
            $submission->evaluation_id,
            $submission->evaluation->module_id,
            $submission->enseignant_id,
            $submission->date_soumission->toIso8601String(),
            $lignesNotes,
        ]);
    }
}