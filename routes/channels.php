<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('filiere.{filiereId}.timetable', function ($user, $filiereId) {
    // TODO: vérifier le rôle réel une fois l'auth/RBAC branchée
    // (responsable pédagogique, enseignant de la filière, délégué de la filière, secrétariat)
    return true;
});