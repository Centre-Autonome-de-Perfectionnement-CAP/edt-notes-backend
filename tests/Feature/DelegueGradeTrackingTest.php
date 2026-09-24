<?php

namespace Tests\Feature;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\GradeTracking\Models\Note;
use App\Modules\Timetable\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegueGradeTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegue_can_view_grades_with_2_days_retention_delay(): void
    {
        $filiere1 = Filiere::create(['nom' => 'Génie Logiciel', 'code' => 'GL']);
        $filiere2 = Filiere::create(['nom' => 'Réseaux & Télécoms', 'code' => 'RT']);

        $delegue = User::factory()->create([
            'name' => 'Délégué GL',
            'role' => 'delegue',
            'filiere_id' => $filiere1->id,
            'telephone' => '+22997112233',
        ]);

        $enseignant = User::factory()->create([
            'role' => 'enseignant',
            'telephone' => '+22997000000',
        ]);

        // Module pour filière 1 (GL)
        $module1 = Module::create([
            'filiere_id' => $filiere1->id,
            'intitule' => 'Architecture Logicielle',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);

        // Module pour filière 2 (RT) - ne doit PAS être visible par le délégué GL
        $module2 = Module::create([
            'filiere_id' => $filiere2->id,
            'intitule' => 'Téléphonie IP',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);

        // Étudiant GL
        $etudiant = Etudiant::create([
            'filiere_id' => $filiere1->id,
            'matricule' => 'GL-001',
            'nom' => 'DOSSO',
            'prenoms' => 'Aïcha',
        ]);

        // Évaluation 1 : soumise il y a 3 jours (déverrouillée > 48h)
        $eval1 = Evaluation::create([
            'module_id' => $module1->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1 Architecture',
        ]);

        $submission1 = GradeSubmission::create([
            'evaluation_id' => $eval1->id,
            'enseignant_id' => $enseignant->id,
            'statut' => 'soumis',
            'qr_hash' => 'hash1',
            'date_soumission' => now()->subDays(3),
        ]);

        Note::create([
            'evaluation_id' => $eval1->id,
            'etudiant_id' => $etudiant->id,
            'valeur' => 16.5,
            'absent' => false,
            'verrouille' => true,
        ]);

        // Évaluation 2 : soumise il y a 6 heures (en attente < 48h)
        $eval2 = Evaluation::create([
            'module_id' => $module1->id,
            'type' => 'examen',
            'libelle' => 'Examen Architecture',
        ]);

        $submission2 = GradeSubmission::create([
            'evaluation_id' => $eval2->id,
            'enseignant_id' => $enseignant->id,
            'statut' => 'soumis',
            'qr_hash' => 'hash2',
            'date_soumission' => now()->subHours(6),
        ]);

        Note::create([
            'evaluation_id' => $eval2->id,
            'etudiant_id' => $etudiant->id,
            'valeur' => 14.0,
            'absent' => false,
            'verrouille' => true,
        ]);

        $response = $this->actingAs($delegue)->getJson('/api/v1/grade-tracking/delegue/notes');

        $response->assertStatus(200);
        $response->assertJsonPath('filiere.code', 'GL');
        $response->assertJsonCount(1, 'data'); // Seul le module GL est renvoyé
        $response->assertJsonPath('data.0.intitule', 'Architecture Logicielle');

        // Vérification Évaluation 1 (> 48h) : disponible avec notes
        $eval1Data = $response->json('data.0.evaluations.0');
        $this->assertEquals('disponible', $eval1Data['statut']);
        $this->assertTrue($eval1Data['delai_ecoule']);
        $this->assertCount(1, $eval1Data['notes']);
        $this->assertEquals(16.5, $eval1Data['notes'][0]['valeur']);
        $this->assertEquals(16.5, $eval1Data['statistiques']['moyenne']);

        // Vérification Évaluation 2 (< 48h) : en attente sans notes
        $eval2Data = $response->json('data.0.evaluations.1');
        $this->assertEquals('en_attente_delai', $eval2Data['statut']);
        $this->assertFalse($eval2Data['delai_ecoule']);
        $this->assertEmpty($eval2Data['notes']);
        $this->assertGreaterThan(0, $eval2Data['heures_restantes']);
    }

    public function test_responsable_can_update_teacher_telephone(): void
    {
        $responsable = User::factory()->create([
            'role' => 'responsable_pedagogique',
        ]);

        $enseignant = User::factory()->create([
            'role' => 'enseignant',
            'telephone' => null,
        ]);

        $response = $this->actingAs($responsable)->patchJson("/api/v1/timetable/enseignants/{$enseignant->id}/telephone", [
            'telephone' => '+229 97 44 55 66',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('+229 97 44 55 66', $enseignant->fresh()->telephone);
    }
}