<?php
// tests/Feature/GradeTracking/SubmitNotesTest.php

namespace Tests\Feature\GradeTracking;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\Timetable\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmitNotesTest extends TestCase
{
    use RefreshDatabase;

    private function creerModuleAvecEnseignant(): Module
    {
        $filiere = Filiere::create(['nom' => 'Génie Logiciel', 'code' => 'GL']);
        $enseignant = User::factory()->create(['role' => 'enseignant']);

        return Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Bases de Données',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);
    }

    private function creerEtudiants(Module $module, int $nombre = 3)
    {
        return collect(range(1, $nombre))->map(fn ($i) => Etudiant::create([
            'filiere_id' => $module->filiere_id,
            'matricule' => 'GL-'.str_pad($i, 3, '0', STR_PAD_LEFT),
            'nom' => "Etudiant$i",
            'prenoms' => 'Test',
        ]));
    }

    public function test_un_enseignant_peut_soumettre_les_notes_de_son_module(): void
    {
        $module = $this->creerModuleAvecEnseignant();
        $etudiants = $this->creerEtudiants($module, 3);
        $evaluation = Evaluation::create([
            'module_id' => $module->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1',
        ]);

        Sanctum::actingAs($module->enseignant, ['*']);

        $response = $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", [
            'notes' => [
                ['etudiant_id' => $etudiants[0]->id, 'valeur' => 14.5, 'absent' => false],
                ['etudiant_id' => $etudiants[1]->id, 'valeur' => 9, 'absent' => false],
                // etudiants[2] volontairement omis → doit devenir absent automatiquement
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notes', [
            'evaluation_id' => $evaluation->id,
            'etudiant_id' => $etudiants[0]->id,
            'valeur' => 14.5,
            'absent' => false,
            'verrouille' => true,
        ]);

        $this->assertDatabaseHas('notes', [
            'evaluation_id' => $evaluation->id,
            'etudiant_id' => $etudiants[2]->id,
            'valeur' => null,
            'absent' => true,
        ]);

        $this->assertDatabaseHas('grade_submissions', [
            'evaluation_id' => $evaluation->id,
            'statut' => 'soumis',
        ]);
    }

    public function test_un_enseignant_ne_peut_pas_soumettre_les_notes_dun_module_qui_nest_pas_le_sien(): void
{
    $module = $this->creerModuleAvecEnseignant();
    $etudiants = $this->creerEtudiants($module, 1);   // ← ajouté, il n'y en avait pas avant
    $evaluation = Evaluation::create([
        'module_id' => $module->id,
        'type' => 'devoir1',
        'libelle' => 'Devoir 1',
    ]);

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    Sanctum::actingAs($autreEnseignant, ['*']);

    $response = $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", [
        'notes' => [['etudiant_id' => $etudiants[0]->id, 'valeur' => 10, 'absent' => false]],
    ]);

    $response->assertForbidden();
}

    public function test_une_double_soumission_est_refusee(): void
    {
        $module = $this->creerModuleAvecEnseignant();
        $etudiants = $this->creerEtudiants($module, 1);
        $evaluation = Evaluation::create([
            'module_id' => $module->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1',
        ]);

        Sanctum::actingAs($module->enseignant, ['*']);

        $payload = ['notes' => [['etudiant_id' => $etudiants[0]->id, 'valeur' => 12, 'absent' => false]]];

        $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", $payload)
            ->assertCreated();

        $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", $payload)
            ->assertStatus(409);
    }

    public function test_un_etudiant_inexistant_est_rejete(): void
    {
        $module = $this->creerModuleAvecEnseignant();
        $evaluation = Evaluation::create([
            'module_id' => $module->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1',
        ]);

        Sanctum::actingAs($module->enseignant, ['*']);

        $response = $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", [
            'notes' => [['etudiant_id' => 9999, 'valeur' => 12, 'absent' => false]],
        ]);

        $response->assertStatus(422);
    }

    public function test_le_roster_retourne_les_etudiants_de_la_filiere(): void
    {
        $module = $this->creerModuleAvecEnseignant();
        $this->creerEtudiants($module, 3);
        $evaluation = Evaluation::create([
            'module_id' => $module->id,
            'type' => 'devoir1',
            'libelle' => 'Devoir 1',
        ]);

        Sanctum::actingAs($module->enseignant, ['*']);

        $response = $this->getJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/roster");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }
}