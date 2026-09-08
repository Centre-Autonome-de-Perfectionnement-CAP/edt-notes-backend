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
            ->orderBy('etudiant_id')
            ->get();

        $qrPayload = json_encode([
            'submission_id' => $submission->id,
            'hash' => $submission->qr_hash,
        ]);

        $qrCodeSvg = QrCode::size(150)->generate($qrPayload);

        $pdf = Pdf::loadView('pdf.recap-notes', [
            'submission' => $submission,
            'notes' => $notes,
            'qrCodeSvg' => $qrCodeSvg,
        ]);

        $path = "grade-recaps/{$submission->evaluation_id}_{$submission->id}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}