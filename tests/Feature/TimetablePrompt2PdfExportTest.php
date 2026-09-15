<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\EmploiDuTemps;
use App\Modules\Timetable\Models\Module;
use App\Modules\Timetable\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetablePrompt2PdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_export_dynamic_grid_with_3_days_only(): void
    {
        $user = User::factory()->create([
            'role' => 'responsable_pedagogique',
            'telephone' => '+22997000001',
        ]);
        $prof1 = User::factory()->create([
            'name' => 'Dr. KOFFI Jean',
            'role' => 'enseignant',
            'telephone' => '+22997112233',
        ]);
        $prof2 = User::factory()->create([
            'name' => 'Prof. DOSSOU Paul',
            'role' => 'enseignant',
            'telephone' => '+22997445566',
        ]);

        $filiere = Filiere::create(['nom' => 'Génie Informatique & Télécoms', 'code' => 'GIT']);

        $mod1 = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Architecture Logicielle',
            'volume_horaire' => 30,
            'enseignant_id' => $prof1->id,
            'couleur' => '#3B82F6',
        ]);

        $mod2 = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Réseaux Sans Fil',
            'volume_horaire' => 25,
            'enseignant_id' => $prof2->id,
            'couleur' => '#10B981',
        ]);

        $emploiDuTemps = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'Semestre 1',
            'date_debut_semaine' => '2026-10-05', // Lundi 05/10/2026
            'date_fin_semaine' => '2026-10-11',   // Dimanche 11/10/2026
            'observation' => 'Séances de rattrapage en salle Info 2.',
            'contact_responsable_nom' => 'Dr. Responsable CAP',
            'contact_responsable_tel' => '+22997000001',
        ]);

        // Création de séances UNIQUEMENT sur 3 jours : Lundi, Mercredi, Samedi
        // Lundi 05/10/2026 (08:00 - 12:00)
        Seance::create([
            'module_id' => $mod1->id,
            'enseignant_id' => $prof1->id,
            'date' => '2026-10-05',
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'salle' => 'Labo Info 1',
            'type' => 'cours',
            'statut' => 'planifie',
        ]);

        // Mercredi 07/10/2026 (14:00 - 18:00)
        Seance::create([
            'module_id' => $mod2->id,
            'enseignant_id' => $prof2->id,
            'date' => '2026-10-07',
            'heure_debut' => '14:00',
            'heure_fin' => '18:00',
            'salle' => 'Salle TD 3',
            'type' => 'tp',
            'statut' => 'planifie',
        ]);

        // Samedi 10/10/2026 (08:00 - 12:00)
        Seance::create([
            'module_id' => $mod1->id,
            'enseignant_id' => $prof1->id,
            'date' => '2026-10-10',
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'salle' => 'Amphi A',
            'type' => 'cours',
            'statut' => 'planifie',
        ]);

        // Appel de l'export PDF
        $response = $this->actingAs($user)->get("/api/v1/timetable/emploi-du-temps/{$emploiDuTemps->id}/export");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // Vérification que le PDF binaire n'est pas vide
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_pdf_export_handles_empty_week(): void
    {
        $user = User::factory()->create([
            'role' => 'responsable_pedagogique',
            'telephone' => '+22997000001',
        ]);
        $filiere = Filiere::create(['nom' => 'Génie Mécanique', 'code' => 'GM']);
        $emploiDuTemps = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'Semestre 2',
            'date_debut_semaine' => '2026-11-02',
            'date_fin_semaine' => '2026-11-08',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/timetable/emploi-du-temps/{$emploiDuTemps->id}/export");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}