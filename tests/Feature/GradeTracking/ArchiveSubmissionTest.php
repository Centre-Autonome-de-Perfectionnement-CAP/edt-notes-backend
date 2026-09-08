<?php
// tests/Feature/GradeTracking/ArchiveSubmissionTest.php

namespace Tests\Feature\GradeTracking;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\Timetable\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchiveSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function creerSoumissionValidee(): GradeSubmission
    {
        $filiere = Filiere::create(['nom' => 'Génie Logiciel', 'code' => 'GL']);
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $module = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Bases de Données',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);
        $etudiant = Etudiant::create([
            'filiere_id' => $filiere->id,
            'matricule' => 'GL-001',
            'nom' => 'Doe',
            'prenoms' => 'Jane',
        ]);
        $evaluation = Evaluation::create([
            'module_id' => $module->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1',
        ]);

        Sanctum::actingAs($enseignant, ['*']);

        $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", [
            'notes' => [['etudiant_id' => $etudiant->id, 'valeur' => 15, 'absent' => false]],
        ])->assertCreated();

        return GradeSubmission::where('evaluation_id', $evaluation->id)->firstOrFail();
    }

    public function test_le_secretariat_peut_archiver_avec_le_bon_hash(): void
    {
        $submission = $this->creerSoumissionValidee();
        $secretaire = User::factory()->create(['role' => 'secretariat']);

        Sanctum::actingAs($secretaire, ['*']);

        $response = $this->patchJson("/api/v1/grade-tracking/submissions/{$submission->id}/archive", [
            'hash' => $submission->qr_hash,
        ]);

        $response->assertOk();
        $response->assertJsonPath('statut', 'archive');

        $this->assertDatabaseHas('grade_submissions', [
            'id' => $submission->id,
            'statut' => 'archive',
            'archive_par' => $secretaire->id,
        ]);
    }

    public function test_un_hash_falsifie_est_rejete(): void
    {
        $submission = $this->creerSoumissionValidee();
        $secretaire = User::factory()->create(['role' => 'secretariat']);

        Sanctum::actingAs($secretaire, ['*']);

        $response = $this->patchJson("/api/v1/grade-tracking/submissions/{$submission->id}/archive", [
            'hash' => 'un-hash-invente-qui-ne-correspond-a-rien',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('grade_submissions', [
            'id' => $submission->id,
            'statut' => 'soumis', // inchangé
        ]);
    }

    public function test_un_enseignant_ne_peut_pas_archiver(): void
    {
        $submission = $this->creerSoumissionValidee();

        Sanctum::actingAs($submission->enseignant, ['*']);

        $response = $this->patchJson("/api/v1/grade-tracking/submissions/{$submission->id}/archive", [
            'hash' => $submission->qr_hash,
        ]);

        $response->assertForbidden();
    }

    public function test_une_soumission_deja_archivee_ne_peut_pas_letre_a_nouveau(): void
    {
        $submission = $this->creerSoumissionValidee();
        $secretaire = User::factory()->create(['role' => 'secretariat']);

        Sanctum::actingAs($secretaire, ['*']);

        $this->patchJson("/api/v1/grade-tracking/submissions/{$submission->id}/archive", [
            'hash' => $submission->qr_hash,
        ])->assertOk();

        $response = $this->patchJson("/api/v1/grade-tracking/submissions/{$submission->id}/archive", [
            'hash' => $submission->qr_hash,
        ]);

        $response->assertStatus(409);
    }
}