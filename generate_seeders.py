import os

timetable_seeder = """<?php

namespace Database\\Seeders;

use App\\Models\\Cycle;
use App\\Models\\Filiere;
use App\\Models\\User;
use App\\Modules\\Timetable\\Models\\Module;
use App\\Modules\\Timetable\\Models\\Seance;
use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\Hash;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = Cycle::firstOrCreate(['libelle' => 'Licence Professionnelle']);

        $filieres = collect([
            ['nom' => 'Génie Logiciel', 'code' => 'GL'],
            ['nom' => 'Réseaux & Télécoms', 'code' => 'RT'],
            ['nom' => 'Gestion des Systèmes d\\'Information', 'code' => 'GSI'],
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
                    'telephone' => '+229 97 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
                ]
            ));
        }

        $modulesData = [
            'GL' => ['Programmation Avancée', 'Bases de Données', 'Génie Logiciel', 'Développement Mobile', 'Algorithmique', 'Architecture des Ordinateurs'],
            'RT' => ['Réseaux sans fil', 'Télécommunications', 'Sécurité Réseaux', 'Administration Linux', 'Fibres Optiques'],
            'GSI' => ['Systèmes d\\'Information', 'Gestion de Projet', 'Audit SI', 'ERP et CRM', 'Management'],
            'GC' => ['Mécanique des Sols', 'Résistance des Matériaux', 'Dessin Bâtiment', 'Topographie', 'Matériaux de Construction'],
            'GE' => ['Électrotechnique', 'Électronique de Puissance', 'Automatique', 'Machines Électriques', 'Circuits Électriques'],
            'GT' => ['Cartographie', 'Systèmes d\\'Information Géographique', 'Géodésie', 'Photogrammétrie', 'Droit Foncier'],
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
            $date = now()->addDays(rand(0, 14))->toDateString();
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
"""

gradetracking_seeder = """<?php

namespace Database\\Seeders;

use App\\Models\\Etudiant;
use App\\Models\\Filiere;
use App\\Models\\User;
use App\\Modules\\GradeTracking\\Models\\Evaluation;
use App\\Modules\\GradeTracking\\Models\\GradeSubmission;
use App\\Modules\\GradeTracking\\Models\\Note;
use App\\Modules\\GradeTracking\\Services\\QrCodeService;
use App\\Modules\\Timetable\\Models\\Module;
use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\Hash;

class GradeTrackingSeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::with('filiere')->get();

        if ($modules->isEmpty()) {
            $this->command->warn('Aucun module trouvé — lance TimetableSeeder avant GradeTrackingSeeder.');
            return;
        }

        $secretariat = User::firstOrCreate(
            ['email' => 'secretariat@cap.test'],
            [
                'name' => 'Secrétariat CAP',
                'password' => Hash::make('password'),
                'role' => 'secretariat',
                'telephone' => '+229 97 00 00 00',
            ]
        );

        User::firstOrCreate(
            ['email' => 'responsable@cap.test'],
            [
                'name' => 'Responsable Pédagogique',
                'password' => Hash::make('password'),
                'role' => 'responsable_pedagogique',
                'telephone' => '+229 97 00 00 99',
            ]
        );

        $filieres = Filiere::all();
        $nomsBeninois = ['Dossou', 'Houngbédji', 'Bio', 'Agbadomé', 'Orou', 'Agbogba', 'Gansè', 'Tossou', 'Kouassi', 'Zannou', 'Adéoti', 'Sossou', 'Gnonlonfoun', 'Koudjo', 'Agbo', 'Hessou', 'Codjo', 'Tidjani', 'Ahouansou', 'Soglo', 'Akindès'];
        $prenomsBeninois = ['Sèna', 'Olabissi', 'Kwami', 'Mahougnon', 'Sèdjro', 'Ayaba', 'Kossi', 'Fifamè', 'Sènami', 'Gbêdonougbo', 'Nassirou', 'Tunde', 'Koffi', 'Yabo', 'Afi', 'Gildas', 'Romaric', 'Sêtondji', 'Sèvi', 'Noukpo', 'Jesugnon', 'Jesukpego'];

        foreach ($filieres as $filiere) {
            User::firstOrCreate(
                ['email' => 'delegue.' . strtolower($filiere->code) . '@cap.test'],
                [
                    'name' => 'Délégué ' . $filiere->nom,
                    'password' => Hash::make('password'),
                    'role' => 'delegue',
                    'filiere_id' => $filiere->id,
                    'telephone' => '+229 97 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
                ]
            );

            $nbEtudiants = $filiere->code === 'GC' ? rand(30, 40) : rand(20, 28);
            
            for ($i = 1; $i <= $nbEtudiants; $i++) {
                $nom = $nomsBeninois[array_rand($nomsBeninois)];
                // 2 to 3 first names
                $nbPrenoms = rand(2, 3);
                $prenoms = [];
                for ($j=0; $j<$nbPrenoms; $j++) {
                    $prenoms[] = $prenomsBeninois[array_rand($prenomsBeninois)];
                }
                $prenomsStr = implode(' ', $prenoms);

                Etudiant::firstOrCreate(
                    ['matricule' => sprintf('%s-%03d', $filiere->code, $i)],
                    [
                        'filiere_id' => $filiere->id,
                        'nom' => $nom,
                        'prenoms' => $prenomsStr,
                    ]
                );
            }
        }

        foreach ($modules as $module) {
            Evaluation::firstOrCreate(
                ['module_id' => $module->id, 'type' => 'devoir1'],
                ['libelle' => 'Devoir 1 — ' . $module->intitule, 'date_prevue' => now()->subDays(10)]
            );
            Evaluation::firstOrCreate(
                ['module_id' => $module->id, 'type' => 'examen'],
                ['libelle' => 'Examen — ' . $module->intitule, 'date_prevue' => now()->subDays(3)]
            );
        }

        $qrService = app(QrCodeService::class);
        $modulesList = $modules->values();

        foreach ($modulesList as $module) {
            $filiereIndex = $filieres->search(fn ($f) => $f->id === $module->filiere_id);
            $evaluations = Evaluation::where('module_id', $module->id)->get();

            foreach ($evaluations as $index => $eval) {
                if ($index === 0) {
                    $this->soumettreNotesDeTest($eval, $module, $qrService, $secretariat, now()->subDays(3), $filiereIndex === 0);
                } elseif ($index === 1 && $filiereIndex === 0) {
                    $this->soumettreNotesDeTest($eval, $module, $qrService, $secretariat, now()->subHours(6), false);
                }
            }
        }

        $this->command->info('GradeTrackingSeeder : délégués, étudiants, évaluations et soumissions créés.');
    }

    private function soumettreNotesDeTest(Evaluation $eval, Module $module, QrCodeService $qrService, User $secretariat, $dateSoumission, bool $archiver): void {
        $submission = GradeSubmission::updateOrCreate(
            ['evaluation_id' => $eval->id],
            [
                'enseignant_id' => $module->enseignant_id,
                'statut' => 'soumis',
                'qr_hash' => '',
                'date_soumission' => $dateSoumission,
            ]
        );

        $etudiants = Etudiant::where('filiere_id', $module->filiere_id)->get();

        foreach ($etudiants as $etudiant) {
            $absent = rand(1, 100) <= 5; // 5% absent
            Note::updateOrCreate(
                [
                    'evaluation_id' => $eval->id,
                    'etudiant_id' => $etudiant->id,
                ],
                [
                    'valeur' => $absent ? null : rand(8, 20),
                    'absent' => $absent,
                ]
            );
        }

        $notes = Note::where('evaluation_id', $eval->id)->get();
        $submission->update(['qr_hash' => $qrService->computeHash($submission, $notes)]);

        if ($archiver) {
            $submission->update(['statut' => 'archive', 'date_archivage' => now(), 'archive_par' => $secretariat->id]);
        }
    }
}
"""

with open("database/seeders/TimetableSeeder.php", "w", encoding="utf-8") as f:
    f.write(timetable_seeder)

with open("database/seeders/GradeTrackingSeeder.php", "w", encoding="utf-8") as f:
    f.write(gradetracking_seeder)

print("Seeders successfully generated.")



