<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

require __DIR__.'/../app/Modules/Timetable/routes/api.php';
require __DIR__.'/../app/Modules/GradeTracking/routes/api.php'; 