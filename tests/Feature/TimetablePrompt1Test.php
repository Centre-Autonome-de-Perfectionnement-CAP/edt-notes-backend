<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetablePrompt1Test extends TestCase
{
    use RefreshDatabase;

    public function test_non_responsable_cannot_create_or_modify_timetable_resources(): void
    {
        $enseignant = User::factory()->create([
            'role' => 'enseignant',
            'telephone' => '+22997000000',
        ]);
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $module = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Algorithmique',
            'volume_horaire' => 30,
            'enseignant_id' => $enseignant->id,
        ]);

        // POST /modules
        $response = $this->actingAs($enseignant)->postJson('/api/v1/timetable/modules', [
            'filiere_id' => $filiere->id,
            'intitule' => 'Base de données',
            'volume_horaire' => 20,
        ]);
        $response->assertStatus(403);

        // PATCH /modules/{module}/couleur
        $response = $this->actingAs($enseignant)->patchJson("/api/v1/timetable/modules/{$module->id}/couleur", [
            'couleur' => '#FF0000',
        ]);
        $response->assertStatus(403);

        // POST /emploi-du-temps
        $response = $this->actingAs($enseignant)->postJson('/api/v1/timetable/emploi-du-temps', [
            'filiere_id' => $filiere->id,
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-01',
            'date_fin_semaine' => '2026-10-07',
        ]);
        $response->assertStatus(403);
    }

    public function test_module_couleur_accessor_and_update_endpoint(): void
    {
        $responsable = User::factory()->create([
            'role' => 'responsable_pedagogique',
            'telephone' => '+22997111111',
        ]);
        $filiere = Filiere::create(['nom' => 'Informatique', 'code' => 'INFO']);
        $module = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Réseaux',
            'volume_horaire' => 25,
            'couleur' => null,
        ]);

        // Accessor returns a color from palette
        $this->assertNotNull($module->couleur);
        $this->assertStringStartsWith('#', $module->couleur);
        $this->assertContains($module->couleur, Module::PALETTE_COULEURS);

        // Invalid hex code
        $resInvalid = $this->actingAs($responsable)->patchJson("/api/v1/timetable/modules/{$module->id}/couleur", [
            'couleur' => 'invalid-color',
        ]);
        $resInvalid->assertStatus(422);

        // Valid hex code
        $resValid = $this->actingAs($responsable)->patchJson("/api/v1/timetable/modules/{$module->id}/couleur", [
            'couleur' => '#12ABEF',
        ]);
        $resValid->assertStatus(200);
        $this->assertEquals('#12ABEF', $module->fresh()->couleur);
    }

    public function test_store_emploi_du_temps_requires_telephone_for_all_assigned_teachers(): void
    {
        $responsable = User::factory()->create([
            'role' => 'responsable_pedagogique',
            'telephone' => '+22997111111',
        ]);
        $enseignantSansTel = User::factory()->create([
            'name' => 'Professeur Sans Tel',
            'role' => 'enseignant',
            'telephone' => null,
        ]);
        $filiere = Filiere::create(['nom' => 'Génie Civil', 'code' => 'GC']);
        Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Résistance des matériaux',
            'volume_horaire' => 40,
            'enseignant_id' => $enseignantSansTel->id,
        ]);

        // Attempt creation with missing telephone
        $res = $this->actingAs($responsable)->postJson('/api/v1/timetable/emploi-du-temps', [
            'filiere_id' => $filiere->id,
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-01',
            'date_fin_semaine' => '2026-10-07',
        ]);

        $res->assertStatus(422);
        $res->assertJsonFragment([
            'message' => 'Numéro manquant pour : Professeur Sans Tel. Complétez les fiches enseignants avant de générer.',
        ]);

        // After updating phone number
        $enseignantSansTel->update(['telephone' => '+22997999999']);

        $resOk = $this->actingAs($responsable)->postJson('/api/v1/timetable/emploi-du-temps', [
            'filiere_id' => $filiere->id,
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-01',
            'date_fin_semaine' => '2026-10-07',
        ]);

        $resOk->assertStatus(201);
    }
}