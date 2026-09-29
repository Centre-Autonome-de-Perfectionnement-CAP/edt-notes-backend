<?php
$user = App\Models\User::where('email', 'LIKE', '%gleason%')->first();
if (!$user) { echo "User not found\n"; exit; }
echo "Found user: {$user->name} ({$user->email})\n";

$evaluations = App\Modules\GradeTracking\Models\Evaluation::whereHas('module', function ($query) use ($user) {
    $query->where('enseignant_id', $user->id);
})->take(2)->get();

if ($evaluations->isEmpty()) {
    echo "No evaluations found for this user\n";
} else {
    foreach ($evaluations as $eval) {
        echo "Evaluation ID: {$eval->id}, Title: {$eval->titre}\n";
        // Delete submissions and notes
        App\Modules\GradeTracking\Models\GradeSubmission::where('evaluation_id', $eval->id)->delete();
        App\Modules\GradeTracking\Models\Note::where('evaluation_id', $eval->id)->delete();
        echo " -> Deleted associated notes and submissions.\n";
    }
}

