<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\Module;
use App\Modules\Timetable\Models\Seance;
use Illuminate\Database\Seeder;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cycle de référence
        $cycle = Cycle::firstOrCreate(['libelle' => 'Licence Professionnelle']);

        // 2. 2-3 filières
        $filieres = collect([
            ['nom' => 'Génie Logiciel', 'code' => 'GL'],
            ['nom' => 'Réseaux & Télécoms', 'code' => 'RT'],
            ['nom' => 'Gestion des Systèmes d\'Information', 'code' => 'GSI'],
        ])->map(fn ($f) => Filiere::firstOrCreate(['code' => $f['code']], $f));

        // 3. Quelques enseignants (users) — créés seulement s'il n'y en a pas déjà
        $enseignants = User::factory()->count(5)->create();

        // 4. 4-5 modules répartis sur les filières
        $modulesData = [
            ['intitule' => 'Développement Web Avancé', 'volume_horaire' => 40],
            ['intitule' => 'Bases de Données', 'volume_horaire' => 30],
            ['intitule' => 'Réseaux Informatiques', 'volume_horaire' => 35],
            ['intitule' => 'Gestion de Projet', 'volume_horaire' => 20],
            ['intitule' => 'Sécurité des Systèmes', 'volume_horaire' => 25],
        ];

        $modules = collect($modulesData)->map(function ($data, $i) use ($filieres, $cycle, $enseignants) {
            return Module::create([
                'filiere_id' => $filieres->get($i % $filieres->count())->id,
                'cycle_id' => $cycle->id,
                'intitule' => $data['intitule'],
                'volume_horaire' => $data['volume_horaire'],
                'enseignant_id' => $enseignants->random()->id,
            ]);
        });

        // 5. Une quinzaine de séances réparties sur les 2 prochaines semaines
        $types = ['cours', 'td', 'tp'];
        $salles = ['A101', 'A102', 'B201', 'B202', 'Labo Info 1'];

        for ($i = 0; $i < 15; $i++) {
            $module = $modules->random();
            $date = now()->addDays(rand(0, 13))->toDateString();
            $heureDebut = sprintf('%02d:00', [8, 10, 14, 16][array_rand([8, 10, 14, 16])]);
            $heureFin = date('H:i', strtotime($heureDebut) + 7200); // +2h

            Seance::create([
                'module_id' => $module->id,
                'enseignant_id' => $module->enseignant_id,
                'date' => $date,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'salle' => $salles[array_rand($salles)],
                'type' => $types[array_rand($types)],
                'statut' => 'planifie',
            ]);
        }

        $this->command->info('TimetableSeeder : '.$filieres->count().' filières, '.$modules->count().' modules, 15 séances créées.');
    }
}