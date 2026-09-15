<?php

namespace App\Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Filiere;
use App\Models\User;
use App\Modules\Timetable\Http\Requests\StoreSeanceRequest;
use App\Modules\Timetable\Http\Requests\UpdateSeanceRequest;
use App\Modules\Timetable\Models\Module;
use App\Modules\Timetable\Models\Seance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\Timetable\Events\SeanceProgrammeeEvent;
use App\Modules\Timetable\Models\EmploiDuTemps;

class TimetableController extends Controller
{
    /**
     * GET /api/v1/timetable/filieres
     */
    public function filieres(): JsonResponse
    {
        return response()->json(['data' => Filiere::all(['id', 'nom', 'code'])]);
    }

    /**
     * GET /api/v1/timetable/filieres/{filiere_id}
     */
    public function byFiliere(Request $request, int $filiere): JsonResponse
    {
        if (! Filiere::whereKey($filiere)->exists()) {
            return response()->json(['message' => 'Filière introuvable.'], 404);
        }

        $query = Seance::forFiliere($filiere)->with(['module.filiere', 'enseignant']);

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date('date_debut')->toDateString());
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date('date_fin')->toDateString());
        }

        return response()->json(['data' => $query->orderBy('date')->orderBy('heure_debut')->get()]);
    }

    /**
     * GET /api/v1/timetable/seances
     */
    public function allSeances(Request $request): JsonResponse
    {
        $query = Seance::with(['module.filiere', 'enseignant']);

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date('date_debut')->toDateString());
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date('date_fin')->toDateString());
        }

        return response()->json(['data' => $query->orderBy('date')->orderBy('heure_debut')->get()]);
    }

    /**
     * GET /api/v1/timetable/enseignants/{enseignant_id}
     */
    public function byEnseignant(Request $request, int $enseignant): JsonResponse
    {
        if (! User::whereKey($enseignant)->exists()) {
            return response()->json(['message' => 'Enseignant introuvable.'], 404);
        }

        $query = Seance::forEnseignant($enseignant)->with(['module', 'module.filiere']);

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date('date_debut')->toDateString());
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date('date_fin')->toDateString());
        }

        return response()->json(['data' => $query->orderBy('date')->orderBy('heure_debut')->get()]);
    }

    /**
     * GET /api/v1/timetable/filieres/{filiere}/modules
     */
    public function modulesByFiliere(int $filiere): JsonResponse
    {
        return response()->json(['data' => Module::where('filiere_id', $filiere)->get(['id', 'intitule'])]);
    }

    /**
     * GET /api/v1/timetable/enseignants
     */
    public function enseignants(): JsonResponse
    {
        return response()->json(['data' => User::where('role', 'enseignant')->get(['id', 'name'])]);
    }

    /**
     * POST /api/v1/timetable/modules
     */
    public function storeModule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'intitule' => ['required', 'string', 'max:255'],
            'volume_horaire' => ['required', 'integer', 'min:1'],
            'enseignant_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $module = Module::create($validated);

        return response()->json(['data' => $module], 201);
    }

    /**
     * PATCH /api/v1/timetable/modules/{module}/couleur
     */
    public function updateCouleur(Request $request, Module $module): JsonResponse
    {
        $validated = $request->validate([
            'couleur' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $module->update(['couleur' => $validated['couleur']]);

        return response()->json([
            'data' => $module->fresh(['filiere', 'cycle', 'enseignant']),
            'message' => 'Couleur du module mise à jour.',
        ]);
    }

    /**
     * POST /api/v1/timetable/seances
     * Réservé au rôle responsable pédagogique.
     */
    public function storeSeance(StoreSeanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($conflict = $this->findConflict($validated['enseignant_id'], $validated['date'], $validated['heure_debut'], $validated['heure_fin'])) {
            return response()->json([
                'message' => 'Conflit horaire : cet enseignant a déjà une séance sur ce créneau.',
                'conflict' => $conflict,
            ], 409);
        }

        $seance = Seance::create($validated + ['statut' => 'planifie']);

        event(new SeanceProgrammeeEvent($seance, 'created')); // branché à l'étape suivante (diffusion temps réel)

        return response()->json(['data' => $seance->load(['module.filiere', 'enseignant'])], 201);
    }

    /**
     * PUT /api/v1/timetable/seances/{id}
     */
    public function updateSeance(UpdateSeanceRequest $request, Seance $seance): JsonResponse
    {
        $validated = $request->validated();

        $enseignantId = $validated['enseignant_id'] ?? $seance->enseignant_id;
        $date = $validated['date'] ?? $seance->date->toDateString();
        $heureDebut = $validated['heure_debut'] ?? $seance->heure_debut;
        $heureFin = $validated['heure_fin'] ?? $seance->heure_fin;

        if ($conflict = $this->findConflict($enseignantId, $date, $heureDebut, $heureFin, excludeSeanceId: $seance->id)) {
            return response()->json([
                'message' => 'Conflit horaire : cet enseignant a déjà une séance sur ce créneau.',
                'conflict' => $conflict,
            ], 409);
        }

        $seance->update($validated);

        event(new SeanceProgrammeeEvent($seance, 'updated'));

        return response()->json(['data' => $seance->fresh(['module.filiere', 'enseignant'])]);
    }

    /**
     * DELETE /api/v1/timetable/seances/{id}
     * Annulation logique : passage statut = annule, pas de suppression physique.
     */
    public function destroySeance(Seance $seance): JsonResponse
    {
        $seance->delete();

        event(new SeanceProgrammeeEvent($seance, 'cancelled'));

        return response()->json(['data' => $seance->fresh(), 'message' => 'Séance annulée.']);
    }

    /**
     * Détecte un chevauchement horaire pour un enseignant donné.
     */
    private function findConflict(int $enseignantId, string $date, string $heureDebut, string $heureFin, ?int $excludeSeanceId = null): ?Seance
    {
        return Seance::where('enseignant_id', $enseignantId)
            ->where('date', $date)
            ->where('statut', '!=', 'annule')
            ->when($excludeSeanceId, fn ($q) => $q->where('id', '!=', $excludeSeanceId))
            ->where(function ($q) use ($heureDebut, $heureFin) {
                $q->where('heure_debut', '<', $heureFin)
                    ->where('heure_fin', '>', $heureDebut);
            })
            ->first();
    }

        /**
     * POST /api/v1/timetable/emploi-du-temps
     * Création de l'en-tête hebdomadaire d'un emploi du temps.
     */
    public function storeEmploiDuTemps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filiere_id' => ['required', 'integer', 'exists:filieres,id'],
            'division' => ['sometimes', 'string', 'max:255'],
            'semestre' => ['required', 'string', 'max:255'],
            'date_debut_semaine' => ['required', 'date'],
            'date_fin_semaine' => ['required', 'date', 'after_or_equal:date_debut_semaine'],
            'observation' => ['nullable', 'string'],
            'contact_responsable_nom' => ['nullable', 'string', 'max:255'],
            'contact_responsable_tel' => ['nullable', 'string', 'max:50'],
        ]);

        $sansTelephone = User::whereIn(
            'id',
            Module::where('filiere_id', $validated['filiere_id'])
                ->whereNotNull('enseignant_id')
                ->pluck('enseignant_id')
        )
            ->whereNull('telephone')
            ->pluck('name');

        if ($sansTelephone->isNotEmpty()) {
            return response()->json([
                'message' => 'Numéro manquant pour : ' . $sansTelephone->implode(', ') . '. Complétez les fiches enseignants avant de générer.',
            ], 422);
        }

        $emploiDuTemps = EmploiDuTemps::create($validated + ['division' => $validated['division'] ?? 'RdivFC']);

        return response()->json(['data' => $emploiDuTemps->load('filiere')], 201);
    }

    /**
     * GET /api/v1/timetable/emploi-du-temps
     * Liste les emplois du temps générés.
     */
    public function listEmploiDuTemps(Request $request): JsonResponse
    {
        $query = EmploiDuTemps::with('filiere');

        if ($request->filled('filiere_id')) {
            $query->where('filiere_id', $request->integer('filiere_id'));
        }

        return response()->json(['data' => $query->latest('date_debut_semaine')->get()]);
    }

    /**
     * GET /api/v1/timetable/emploi-du-temps/latest
     * Récupère le dernier emploi du temps pour une filière (ou global).
     */
    public function latestEmploiDuTemps(Request $request): JsonResponse
    {
        $query = EmploiDuTemps::with('filiere');

        if ($request->filled('filiere_id')) {
            $query->where('filiere_id', $request->integer('filiere_id'));
        }

        $latest = $query->latest('date_debut_semaine')->first();

        if (! $latest) {
            return response()->json(['message' => 'Aucun emploi du temps trouvé.'], 404);
        }

        return response()->json(['data' => $latest]);
    }
}