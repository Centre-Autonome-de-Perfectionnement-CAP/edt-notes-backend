<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\EmploiDuTemps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetablePrompt5Test extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_list_and_get_latest_emploi_du_temps(): void
    {
        $delegue = User::factory()->create(['role' => 'delegue']);
        $filiere = Filiere::create(['nom' => 'Génie Informatique', 'code' => 'GI']);

        $edt1 = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-05',
            'date_fin_semaine' => '2026-10-11',
        ]);

        $edt2 = EmploiDuTemps::create([
            'filiere_id' => $filiere->id,
            'division' => 'RdivFC',
            'semestre' => 'S1',
            'date_debut_semaine' => '2026-10-12',
            'date_fin_semaine' => '2026-10-18',
        ]);

        // List
        $responseList = $this->actingAs($delegue)->getJson('/api/v1/timetable/emploi-du-temps?filiere_id=' . $filiere->id);
        $responseList->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Latest
        $responseLatest = $this->actingAs($delegue)->getJson('/api/v1/timetable/emploi-du-temps/latest?filiere_id=' . $filiere->id);
        $responseLatest->assertStatus(200)
            ->assertJsonPath('data.id', $edt2->id);
    }
}