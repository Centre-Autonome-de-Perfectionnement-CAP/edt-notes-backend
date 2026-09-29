<?php

namespace Database\Seeders;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\GradeTracking\Models\Evaluation;
use App\Modules\GradeTracking\Models\GradeSubmission;
use App\Modules\GradeTracking\Models\Note;
use App\Modules\GradeTracking\Services\QrCodeService;
use App\Modules\Timetable\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
