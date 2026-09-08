<?php

namespace App\Modules\GradeTracking\Services;

use App\Modules\GradeTracking\Models\Evaluation;
use App\Models\Filiere;


class GradeTrackingService
{
    /**
     * Une filière est "complète" quand toutes ses évaluations existantes
     * ont une soumission verrouillée (soumis ou archive).
     */
    public function isFiliereComplete(int $filiereId): bool
    {
        $totalEvaluations = Evaluation::whereHas('module', fn ($q) => $q->where('filiere_id', $filiereId))
            ->count();

        if ($totalEvaluations === 0) {
            return false;
        }

        $nonSoumises = Evaluation::whereHas('module', fn ($q) => $q->where('filiere_id', $filiereId))
            ->whereDoesntHave('gradeSubmission')
            ->count();

        return $nonSoumises === 0;
    }

/**
 * Détail par module/évaluation pour une filière — alimente l'écran
 * de détail du responsable pédagogique.
 */
public function computeFiliereStatus(int $filiereId): array
{
    $evaluations = Evaluation::whereHas('module', fn ($q) => $q->where('filiere_id', $filiereId))
        ->with(['module', 'gradeSubmission'])
        ->get();

    $total = $evaluations->count();
    $soumises = $evaluations->filter(fn ($e) => $e->gradeSubmission !== null)->count();
    $pourcentage = $total > 0 ? round(($soumises / $total) * 100, 1) : 0;

    $modules = $evaluations->groupBy('module_id')->map(function ($evaluationsDuModule) {
        $module = $evaluationsDuModule->first()->module;

        return [
            'module_id' => $module->id,
            'intitule' => $module->intitule,
            'evaluations' => $evaluationsDuModule->map(fn ($e) => [
                'evaluation_id' => $e->id,
                'type' => $e->type,
                'statut_soumission' => $e->gradeSubmission->statut ?? 'non_soumis',
            ])->values(),
        ];
    })->values();

    return [
        'filiere_id' => $filiereId,
        'statut' => $this->statutCouleur($pourcentage, $total),
        'pourcentage_complete' => $pourcentage,
        'modules' => $modules,
    ];
}

/**
 * Vue globale toutes filières, pour le dashboard du responsable pédagogique.
 */
public function dashboardData(): array
{
    return Filiere::all()->map(function ($filiere) {
        $status = $this->computeFiliereStatus($filiere->id);

        return [
            'filiere_id' => $filiere->id,
            'nom' => $filiere->nom,
            'statut' => $status['statut'],
            'pourcentage_complete' => $status['pourcentage_complete'],
        ];
    })->values()->toArray();
}

private function statutCouleur(float $pourcentage, int $totalEvaluations): string
{
    if ($totalEvaluations === 0) {
        return 'rouge'; // aucune évaluation créée pour cette filière — rien à afficher comme "prêt"
    }

    return match (true) {
        $pourcentage >= 100 => 'vert',
        $pourcentage > 0 => 'orange',
        default => 'rouge',
    };
}
}