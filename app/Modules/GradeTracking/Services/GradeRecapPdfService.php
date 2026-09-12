<?php
// app/Modules/GradeTracking/Services/GradeRecapPdfService.php

namespace App\Modules\GradeTracking\Services;

use App\Modules\GradeTracking\Models\GradeSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GradeRecapPdfService
{
    public function generate(GradeSubmission $submission): string
    {
        $submission->load(['evaluation.module.filiere', 'enseignant']);

        $notes = $submission->evaluation->notes()
            ->with('etudiant')
            ->get()
            ->sortBy(fn ($note) => $note->etudiant->nom)
            ->values();

        $qrPayload = json_encode([
            'submission_id' => $submission->id,
            'hash' => $submission->qr_hash,
        ]);

        $qrSvg = QrCode::format('svg')->size(150)->generate($qrPayload);
        $qrBase64 = base64_encode($qrSvg);

        $dateSoumissionFormatee = $submission->date_soumission->setTimezone('Africa/Porto-Novo')->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.recap-notes', [
            'submission' => $submission,
            'notes' => $notes,
            'qrBase64' => $qrBase64,
            'dateSoumissionFormatee' => $dateSoumissionFormatee,
        ]);

        $path = "grade-recaps/{$submission->evaluation_id}_{$submission->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}