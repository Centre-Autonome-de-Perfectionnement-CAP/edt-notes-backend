<?php
// app/Modules/GradeTracking/routes/api.php

use App\Modules\GradeTracking\Http\Controllers\GradeTrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/grade-tracking')->middleware('auth:sanctum')->group(function () {
    Route::get('/evaluations/{evaluation}/roster', [GradeTrackingController::class, 'roster']);
    Route::post('/evaluations/{evaluation}/notes', [GradeTrackingController::class, 'submitNotes']);
    Route::get('/filieres/{filiere}/status', [GradeTrackingController::class, 'filiereStatus']);
    Route::get('/dashboard', [GradeTrackingController::class, 'dashboard']);
    Route::get('/submissions/{submission}/pdf', [GradeTrackingController::class, 'downloadPdf']);
Route::patch('/submissions/{submission}/archive', [GradeTrackingController::class, 'archive']);
});