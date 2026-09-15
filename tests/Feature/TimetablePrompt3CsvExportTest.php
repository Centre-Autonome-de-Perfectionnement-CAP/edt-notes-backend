<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\EmploiDuTemps;
use App\Modules\Timetable\Models\Module;
use App\Modules\Timetable\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetablePrompt3CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_streams_flat_csv_with_all_seances(): void
    {
        $delegue = User::factory()->create([
            'role' => 'delegue',
        ]);
        $prof = User::factory()->create([
            'name' => 'Dr. KOFFI Jean',
            'role' => 'enseignant',
            'telephone' => '+22997112233',
        ]);

        $filiere = Filiere::create(['nom' => 'Génie Informatique', 'code' => 'GI']);

        $module = Module::create([
            'filiere_id' => $filiere->id,
            'intitule' => 'Algorithmique Avancée',
            'volume_horaire' => 30,
            'enseignant_id' => $prof->id,
            'couleur' => '#3B82F6',
        ]);

        $emploiDuTemps = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-05',
            'date_fin_semaine' => '2026-10-11',
        ]);

        Seance::create([
            'module_id' => $module->id,
            'enseignant_id' => $prof->id,
            'date' => '2026-10-05',
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'salle' => 'Labo 1',
            'type' => 'cours',
            'statut' => 'planifie',
        ]);

        $response = $this->actingAs($delegue)->get("/api/v1/timetable/emploi-du-temps/{$emploiDuTemps->id}/export-csv");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('emploi-du-temps_gi_2026-10-05.csv', $response->headers->get('Content-Disposition'));

        // Stream output check
        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('jour,date,heure_debut,heure_fin,module,salle,enseignant,telephone_enseignant', $csvContent);
        $this->assertStringContainsString('2026-10-05', $csvContent);
        $this->assertStringContainsString('08:00', $csvContent);
        $this->assertStringContainsString('12:00', $csvContent);
        $this->assertStringContainsString('Algorithmique Avancée', $csvContent);
        $this->assertStringContainsString('Labo 1', $csvContent);
        $this->assertStringContainsString('Dr. KOFFI Jean', $csvContent);
        $this->assertStringContainsString('+22997112233', $csvContent);
    }

    public function test_csv_export_unauthenticated_returns_401(): void
    {
        $filiere = Filiere::create(['nom' => 'Génie Informatique', 'code' => 'GI']);
        $emploiDuTemps = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-05',
            'date_fin_semaine' => '2026-10-11',
        ]);

        $response = $this->getJson("/api/v1/timetable/emploi-du-temps/{$emploiDuTemps->id}/export-csv");
        $response->assertStatus(401);
    }
}