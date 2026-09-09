<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('filiere.{filiereId}.timetable', function ($user, $filiereId) {
    // TODO: vérifier le rôle réel une fois l'auth/RBAC branchée
    // (responsable pédagogique, enseignant de la filière, délégué de la filière, secrétariat)
    return true;
});

Broadcast::channel('filiere.{filiereId}.grades', function ($user, $filiereId) {
    // TODO: restreindre au responsable pédagogique et secrétariat une
    // fois le RBAC canal-par-canal cadré avec l'équipe.
    return true;
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);