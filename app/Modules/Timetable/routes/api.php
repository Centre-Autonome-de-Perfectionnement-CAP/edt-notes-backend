<?php

use App\Modules\Timetable\Http\Controllers\TimetableController;
use App\Modules\Timetable\Http\Controllers\TimetableExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/timetable')->middleware('auth:sanctum')->group(function () {
    Route::get('/filieres', [TimetableController::class, 'filieres']);
    Route::get('/filieres/{filiere}', [TimetableController::class, 'byFiliere']);
    Route::get('/filieres/{filiere}/modules', [TimetableController::class, 'modulesByFiliere']);
    Route::get('/enseignants', [TimetableController::class, 'enseignants']);
    Route::get('/enseignants/{enseignant}', [TimetableController::class, 'byEnseignant']);
    Route::get('/seances', [TimetableController::class, 'allSeances']);

    Route::post('/modules', [TimetableController::class, 'storeModule']);

    Route::post('/seances', [TimetableController::class, 'storeSeance']);
    Route::put('/seances/{seance}', [TimetableController::class, 'updateSeance']);
    Route::delete('/seances/{seance}', [TimetableController::class, 'destroySeance']);

    Route::post('/emploi-du-temps', [TimetableController::class, 'storeEmploiDuTemps']);
    Route::get('/emploi-du-temps/{emploiDuTemps}/export', [TimetableExportController::class, 'export']);
});