<?php
// database/seeders/GradeTrackingSeeder.php

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

        $modules->pluck('enseignant_id')->unique()->each(
            fn ($id) => User::whereKey($id)->update([
                'role' => 'enseignant',
                'telephone' => '+229 97 ' . fake()->numerify('## ## ##'),
            ])
        );

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

        User::whereNull('telephone')->orWhere('telephone', '')->each(
            fn ($u) => $u->update(['telephone' => '+229 97 ' . fake()->numerify('## ## ##')])
        );

        // 1. Étudiants & Délégué par filière
        $filieres = Filiere::all();

        foreach ($filieres as $filiere) {
            // Créer un délégué (responsable de classe) pour la filière
            User::firstOrCreate(
                ['email' => 'delegue.' . strtolower($filiere->code) . '@cap.test'],
                [
                    'name' => 'Délégué ' . $filiere->nom,
                    'password' => Hash::make('password'),
                    'role' => 'delegue',
                    'filiere_id' => $filiere->id,
                    'telephone' => '+229 97 ' . fake()->numerify('## ## ##'),
                ]
            );

            for ($i = 1; $i <= 6; $i++) {
                Etudiant::firstOrCreate(
                    ['matricule' => sprintf('%s-%03d', $filiere->code, $i)],
                    [
                        'filiere_id' => $filiere->id,
                        'nom' => fake()->lastName(),
                        'prenoms' => fake()->firstName(),
                    ]
                );
            }
        }

        // 2. Évaluations — 2 par module (devoir1 + examen)
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
                // Devoir 1 : soumis il y a 3 jours (déverrouillé > 48h)
                // Examen : soumis il y a 6 heures (en attente < 48h)
                if ($index === 0) {
                    $this->soumettreNotesDeTest($eval, $module, $qrService, $secretariat, dateSoumission: now()->subDays(3), archiver: $filiereIndex === 0);
                } elseif ($index === 1 && $filiereIndex === 0) {
                    $this->soumettreNotesDeTest($eval, $module, $qrService, $secretariat, dateSoumission: now()->subHours(6), archiver: false);
                }
            }
        }

        $this->command->info('GradeTrackingSeeder : délégués, étudiants, évaluations et soumissions de test créés.');
        $this->command->info('Comptes de test (mdp: password) :');
        $this->command->info(' - secretariat@cap.test');
        $this->command->info(' - responsable@cap.test');
        $this->command->info(' - delegue.gl@cap.test, delegue.rt@cap.test, delegue.gsi@cap.test');
    }

    private function soumettreNotesDeTest(
        Evaluation $eval,
        Module $module,
        QrCodeService $qrService,
        User $secretariat,
        $dateSoumission,
        bool $archiver
    ): void {
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
            $absent = fake()->boolean(10);

            Note::updateOrCreate(
                [
                    'evaluation_id' => $eval->id,
                    'etudiant_id' => $etudiant->id,
                ],
                [
                    'valeur' => $absent ? null : fake()->randomFloat(2, 8, 19),
                    'absent' => $absent,
                    'verrouille' => true,
                ]
            );
        }

        $qrHash = $qrService->computeHash($submission, $eval->notes()->get());
        $submission->update(['qr_hash' => $qrHash]);

        if ($archiver) {
            $submission->update([
                'statut' => 'archive',
                'date_archivage' => now()->subDay(),
                'archive_par' => $secretariat->id,
            ]);
        }
    }
}