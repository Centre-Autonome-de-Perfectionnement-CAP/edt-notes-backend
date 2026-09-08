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

class GradeTrackingSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Comptes de test avec rôle — le UserFactory ne pose pas de rôle
        //    par défaut, donc les enseignants créés par TimetableSeeder ont
        //    role = null tant qu'on ne le corrige pas ici.
        $modules = Module::with('filiere')->get();

        if ($modules->isEmpty()) {
            $this->command->warn('Aucun module trouvé — lance TimetableSeeder avant GradeTrackingSeeder.');
            return;
        }

        $modules->pluck('enseignant_id')->unique()->each(
            fn ($id) => User::whereKey($id)->update(['role' => 'enseignant'])
        );

        $secretaire = User::factory()->create([
            'name' => 'Secrétariat CAP',
            'email' => 'secretariat@cap.test',
            'role' => 'secretariat',
        ]);

        User::factory()->create([
            'name' => 'Responsable Pédagogique',
            'email' => 'responsable@cap.test',
            'role' => 'responsable_pedagogique',
        ]);

        // 1. Étudiants — 6 par filière
        $filieres = Filiere::all();

        foreach ($filieres as $filiere) {
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
                ['libelle' => 'Devoir 1 — '.$module->intitule, 'date_prevue' => now()->subDays(10)]
            );
            Evaluation::firstOrCreate(
                ['module_id' => $module->id, 'type' => 'examen'],
                ['libelle' => 'Examen — '.$module->intitule, 'date_prevue' => now()->subDays(3)]
            );
        }

        // 3. États volontairement différents selon la filière, pour tester
        //    les 3 couleurs du dashboard (vert/orange/rouge) sans avoir à
        //    soumettre quoi que ce soit manuellement.
        $qrCodeService = app(QrCodeService::class);
        $filieresIndexees = $filieres->values();

        foreach ($modules as $module) {
            $indexFiliere = $filieresIndexees->search(fn ($f) => $f->id === $module->filiere_id);
            $evaluations = Evaluation::where('module_id', $module->id)->get();

            foreach ($evaluations as $i => $evaluation) {
                // Filière 0 → tout soumis (vert). Filière 1 → un devoir sur deux (orange).
                // Filière 2+ → rien de soumis (rouge).
                $doitEtreSoumis = match ($indexFiliere) {
                    0 => true,
                    1 => $i === 0,
                    default => false,
                };

                if (! $doitEtreSoumis) {
                    continue;
                }

                $this->soumettreNotesDeTest($evaluation, $module, $qrCodeService, $secretaire, archiver: $indexFiliere === 0);
            }
        }

        $this->command->info('GradeTrackingSeeder : étudiants, évaluations et soumissions de test créés.');
        $this->command->info('Comptes : secretariat@cap.test / responsable@cap.test (mot de passe : password)');
    }

    private function soumettreNotesDeTest(
        Evaluation $evaluation,
        Module $module,
        QrCodeService $qrCodeService,
        User $secretaire,
        bool $archiver
    ): void {
        $submission = GradeSubmission::create([
            'evaluation_id' => $evaluation->id,
            'enseignant_id' => $module->enseignant_id,
            'statut' => 'soumis',
            'qr_hash' => '',
            'date_soumission' => now()->subDays(2),
        ]);

        $etudiants = Etudiant::where('filiere_id', $module->filiere_id)->get();

        foreach ($etudiants as $etudiant) {
            $estAbsent = fake()->boolean(10); // 10% d'absents, pour avoir un cas de test

            Note::create([
                'evaluation_id' => $evaluation->id,
                'etudiant_id' => $etudiant->id,
                'valeur' => $estAbsent ? null : fake()->randomFloat(2, 5, 20),
                'absent' => $estAbsent,
                'verrouille' => true,
            ]);
        }

        $hash = $qrCodeService->computeHash($submission, $evaluation->notes()->get());
        $submission->update(['qr_hash' => $hash]);

        if ($archiver) {
            $submission->update([
                'statut' => 'archive',
                'date_archivage' => now()->subDay(),
                'archive_par' => $secretaire->id,
            ]);
        }

        // Pas de génération de PDF ici volontairement — pdf_path reste null
        // pour les données de seed. Seul un vrai appel à submitNotes() en
        // génère un (DomPDF à chaque exécution du seeder serait inutile ici).
    }
}