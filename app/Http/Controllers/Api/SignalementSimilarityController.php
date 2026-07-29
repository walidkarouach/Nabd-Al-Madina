<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use App\Services\AI\SignalementSimilarityService;
use Illuminate\Http\JsonResponse;
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
}
