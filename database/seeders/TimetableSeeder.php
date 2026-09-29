<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Models\Module;
use App\Modules\Timetable\Models\Seance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = Cycle::firstOrCreate(['libelle' => 'Licence Professionnelle']);

        $filieres = collect([
            ['nom' => 'Génie Logiciel', 'code' => 'GL'],
            ['nom' => 'Réseaux & Télécoms', 'code' => 'RT'],
            ['nom' => 'Gestion des Systèmes d\'Information', 'code' => 'GSI'],
            ['nom' => 'Génie Civil', 'code' => 'GC'],
            ['nom' => 'Génie Électrique', 'code' => 'GE'],
            ['nom' => 'Géomètre Topographe', 'code' => 'GT'],
            ['nom' => 'Génie Mécanique et Énergétique', 'code' => 'GME'],
        ])->map(fn ($f) => Filiere::firstOrCreate(['code' => $f['code']], $f));

        $nomsBeninois = ['Dossou', 'Houngbédji', 'Bio', 'Agbadomé', 'Orou', 'Agbogba', 'Gansè', 'Tossou', 'Kouassi', 'Zannou', 'Adéoti', 'Sossou', 'Gnonlonfoun', 'Koudjo', 'Agbo', 'Hessou', 'Codjo', 'Tidjani', 'Ahouansou', 'Soglo', 'Akindès'];
        $prenomsBeninois = ['Sèna', 'Olabissi', 'Kwami', 'Mahougnon', 'Sèdjro', 'Ayaba', 'Kossi', 'Fifamè', 'Sènami', 'Gbêdonougbo', 'Nassirou', 'Tunde', 'Koffi', 'Yabo', 'Afi', 'Gildas', 'Romaric', 'Sêtondji', 'Sèvi', 'Noukpo'];

        // Créer 25 enseignants
        $enseignants = collect();
        for ($i = 0; $i < 25; $i++) {
            $nom = $nomsBeninois[array_rand($nomsBeninois)];
            $prenom = $prenomsBeninois[array_rand($prenomsBeninois)];
            // Avoid duplicate email with uniqid
            $email = strtolower($prenom . '.' . $nom . $i . '@cap.test');
            // Remove accents and spaces for email
            $email = str_replace([' ', 'é', 'è', 'ê', 'à', 'â'], ['', 'e', 'e', 'e', 'a', 'a'], $email);
            
            $enseignants->push(User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $prenom . ' ' . $nom,
                    'password' => Hash::make('password'),
                    'role' => 'enseignant',
                    'telephone' => '01' . rand(40, 99) . rand(10, 99) . rand(10, 99) . rand(10, 99),
                ]
            ));
        }

        $modulesData = [
            'GL' => ['Programmation Avancée', 'Bases de Données', 'Génie Logiciel', 'Développement Mobile', 'Algorithmique', 'Architecture des Ordinateurs'],
            'RT' => ['Réseaux sans fil', 'Télécommunications', 'Sécurité Réseaux', 'Administration Linux', 'Fibres Optiques'],
            'GSI' => ['Systèmes d\'Information', 'Gestion de Projet', 'Audit SI', 'ERP et CRM', 'Management'],
            'GC' => ['Mécanique des Sols', 'Résistance des Matériaux', 'Dessin Bâtiment', 'Topographie', 'Matériaux de Construction'],
            'GE' => ['Électrotechnique', 'Électronique de Puissance', 'Automatique', 'Machines Électriques', 'Circuits Électriques'],
            'GT' => ['Cartographie', 'Systèmes d\'Information Géographique', 'Géodésie', 'Photogrammétrie', 'Droit Foncier'],
            'GME' => ['Thermodynamique', 'Mécanique des Fluides', 'Transfert Thermique', 'Machines Thermiques', 'Dessin Industriel'],
        ];

        $modules = collect();
        foreach ($filieres as $filiere) {
            $intitules = $modulesData[$filiere->code];
            // Sélectionner 3 à 4 enseignants uniques pour cette filière
            $enseignantsFiliere = $enseignants->random(4);
            foreach ($intitules as $idx => $intitule) {
                $modules->push(Module::firstOrCreate(
                    ['filiere_id' => $filiere->id, 'intitule' => $intitule],
                    [
                        'cycle_id' => $cycle->id,
                        'volume_horaire' => rand(20, 40),
                        'enseignant_id' => $enseignantsFiliere->get($idx % 4)->id,
                    ]
                ));
            }
        }

        // Séances
        $types = ['cours', 'td', 'tp'];
        $salles = ['A101', 'A102', 'B201', 'B202', 'Labo Info 1', 'Amphi A', 'Amphi B'];

        for ($i = 0; $i < 100; $i++) {
            $module = $modules->random();
            $dateObj = now()->addDays(rand(0, 14)); while ($dateObj->isWeekend()) { $dateObj->addDay(); } $date = $dateObj->toDateString();
            $heureDebut = sprintf('%02d:00', [8, 10, 14, 16][array_rand([8, 10, 14, 16])]);
            $heureFin = date('H:i', strtotime($heureDebut) + 7200);

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

        $this->command->info('TimetableSeeder : '.$filieres->count().' filières, '.$modules->count().' modules, 100 séances créées.');
    }
}
