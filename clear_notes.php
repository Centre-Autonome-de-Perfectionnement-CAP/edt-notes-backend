<?php
$user = App\Models\User::where('name', 'like', '%Terrence Pfeffer%')->first();
if ($user) {
    $moduleIds = App\Modules\Timetable\Models\Module::where('enseignant_id', $user->id)->pluck('id');
    $evals = App\Modules\GradeTracking\Models\Evaluation::whereIn('module_id', $moduleIds)->where('libelle', 'like', '%Devoir 1%')->get();
    foreach ($evals as $eval) {
        $deleted = App\Modules\GradeTracking\Models\Note::where('evaluation_id', $eval->id)->delete();
        echo 'Deleted ' . $deleted . ' notes for evaluation ' . $eval->id . PHP_EOL;
        $eval->update(['statut' => 'attente_notes']);
    }
} else {
    echo "User not found\n";
}
