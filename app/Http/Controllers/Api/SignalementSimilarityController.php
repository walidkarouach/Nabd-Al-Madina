<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Signalement;
use App\Services\AI\SignalementSimilarityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SignalementSimilarityController extends Controller
{
    /**
     * Rechercher et renvoyer les signalements similaires pour un signalement donné.
     * Réservé aux agents municipaux (middleware role:agent_municipal & policy viewSimilaires).
     */
    public function index(Signalement $signalement, SignalementSimilarityService $service): JsonResponse
    {
        Gate::authorize('viewSimilaires', $signalement);

        $similarities = $service->findCandidates($signalement);

        return response()->json([
            'signalement_cible_id' => $signalement->id,
            'nombre_candidats' => count($similarities),
            'similaires' => $similarities,
        ]);
    }

    /**
     * Valider le regroupement de signalements sous un incident.
     */
    public function validateGrouping(Request $request, Signalement $signalement): JsonResponse
    {
        // 1. Vérifie via la Policy que l'agent a le droit d'agir sur ce signalement
        Gate::authorize('update', $signalement);

        // Validation des entrées
        $validated = $request->validate([
            'incident_id' => ['nullable', 'integer', 'exists:incidents,id'],
            'signalement_ids' => ['nullable', 'array'],
            'signalement_ids.*' => ['integer', 'exists:signalements,id'],
            'titre' => ['required_without:incident_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // 2. Si incident_id est fourni → rattache à un incident existant (et revérifie la Policy dessus)
        if (!empty($validated['incident_id'])) {
            $incident = Incident::findOrFail($validated['incident_id']);
            Gate::authorize('validateGrouping', $incident);
        } else {
            // 3. Sinon → crée un nouvel Incident
            $incident = Incident::create([
                'titre' => $validated['titre'],
                'description' => $validated['description'] ?? null,
                'department_id' => $signalement->department_id,
                'validated_by' => $user->id,
            ]);
        }

        // 4. Rattache tous les signalements listés (signalement_ids + le signalement courant) à cet incident,
        // uniquement s'ils sont du même département que l'incident.
        $signalementIds = $validated['signalement_ids'] ?? [];
        if (!in_array($signalement->id, $signalementIds)) {
            $signalementIds[] = $signalement->id;
        }

        $signalementsToAttach = Signalement::whereIn('id', $signalementIds)
            ->where('department_id', $incident->department_id)
            ->get();

        foreach ($signalementsToAttach as $sig) {
            $sig->update(['incident_id' => $incident->id]);
        }

        return response()->json([
            'message' => 'Regroupement validé avec succès.',
            'incident' => $incident->load(['signalements', 'departement', 'validateur']),
        ]);
    }
}

