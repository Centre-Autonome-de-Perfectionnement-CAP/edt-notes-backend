<?php
// tests/Feature/GradeTracking/DashboardTest.php

namespace Tests\Feature\GradeTracking;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\Timetable\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function creerFiliereAvecModule(string $nomFiliere): array
    {
        $filiere = Filiere::create(['nom' => $nomFiliere, 'code' => strtoupper(substr($nomFiliere, 0, 3))]);
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $module = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Module Test',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);
        $etudiant = Etudiant::create([
            'filiere_id' => $filiere->id,
            'matricule' => strtoupper(substr($nomFiliere, 0, 3)).'-001',
            'nom' => 'Doe',
            'prenoms' => 'Jane',
        ]);

        return compact('filiere', 'enseignant', 'module', 'etudiant');
    }

    private function soumettre(Evaluation $evaluation, User $enseignant, Etudiant $etudiant): void
    {
        Sanctum::actingAs($enseignant, ['*']);

        $this->postJson("/api/v1/grade-tracking/evaluations/{$evaluation->id}/notes", [
            'notes' => [['etudiant_id' => $etudiant->id, 'valeur' => 14, 'absent' => false]],
        ])->assertCreated();
    }

    public function test_une_filiere_sans_aucune_evaluation_est_rouge(): void
    {
        ['filiere' => $filiere] = $this->creerFiliereAvecModule('Rouge');

        $responsable = User::factory()->create(['role' => 'responsable_pedagogique']);
        Sanctum::actingAs($responsable, ['*']);

        $response = $this->getJson("/api/v1/grade-tracking/filieres/{$filiere->id}/status");

        $response->assertOk();
        $response->assertJsonPath('statut', 'rouge');
        $response->assertJsonPath('pourcentage_complete', 0);
    }

    public function test_une_filiere_partiellement_soumise_est_orange(): void
{
    ['filiere' => $filiere, 'module' => $module, 'enseignant' => $enseignant, 'etudiant' => $etudiant]
        = $this->creerFiliereAvecModule('Orange');

    $devoir1 = Evaluation::create(['module_id' => $module->id, 'type' => 'devoir1', 'libelle' => 'Devoir 1']);
    Evaluation::create(['module_id' => $module->id, 'type' => 'examen', 'libelle' => 'Examen']); // non soumis

    $this->soumettre($devoir1, $enseignant, $etudiant);

    $responsable = User::factory()->create(['role' => 'responsable_pedagogique']);
    Sanctum::actingAs($responsable, ['*']);

    $response = $this->getJson("/api/v1/grade-tracking/filieres/{$filiere->id}/status");

    $response->assertOk();
    $response->assertJsonPath('statut', 'orange');
    $this->assertEquals(50, $response->json('pourcentage_complete'));
}

    public function test_une_filiere_entierement_soumise_est_verte(): void
    {
        ['filiere' => $filiere, 'module' => $module, 'enseignant' => $enseignant, 'etudiant' => $etudiant]
            = $this->creerFiliereAvecModule('Verte');

        $devoir1 = Evaluation::create(['module_id' => $module->id, 'type' => 'devoir1', 'libelle' => 'Devoir 1']);
        $examen = Evaluation::create(['module_id' => $module->id, 'type' => 'examen', 'libelle' => 'Examen']);

        $this->soumettre($devoir1, $enseignant, $etudiant);
        $this->soumettre($examen, $enseignant, $etudiant);

        $responsable = User::factory()->create(['role' => 'responsable_pedagogique']);
        Sanctum::actingAs($responsable, ['*']);

        $response = $this->getJson("/api/v1/grade-tracking/filieres/{$filiere->id}/status");

        $response->assertOk();
        $response->assertJsonPath('statut', 'vert');
        $this->assertEquals(100, $response->json('pourcentage_complete'));
    }

    public function test_le_dashboard_retourne_toutes_les_filieres_avec_leur_statut(): void
    {
        ['module' => $moduleVert, 'enseignant' => $ensVert, 'etudiant' => $etuVert]
            = $this->creerFiliereAvecModule('Verte');
        $this->creerFiliereAvecModule('Rouge');

        $evaluation = Evaluation::create(['module_id' => $moduleVert->id, 'type' => 'devoir1', 'libelle' => 'Devoir 1']);
        $this->soumettre($evaluation, $ensVert, $etuVert);

        $responsable = User::factory()->create(['role' => 'responsable_pedagogique']);
        Sanctum::actingAs($responsable, ['*']);

        $response = $this->getJson('/api/v1/grade-tracking/dashboard');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $statuts = collect($response->json('data'))->pluck('statut', 'nom');
        $this->assertEquals('vert', $statuts['Verte']);
        $this->assertEquals('rouge', $statuts['Rouge']);
    }
}