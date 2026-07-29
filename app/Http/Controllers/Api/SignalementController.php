<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SignalementController extends Controller
{
    /**
     * Consulter un signalement.
     */
    public function show(Signalement $signalement)
    {
        Gate::authorize('view', $signalement);

        return response()->json($signalement);
    }

    /**
     * Modifier le statut d'un signalement.
     */
    public function updateStatus(Request $request, Signalement $signalement)
    {
        Gate::authorize('update', $signalement);

        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $signalement->update([
            'status' => $validated['status'],
        ]);

        return response()->json($signalement);
    }

    /**
     * Modifier un signalement (mise à jour complète ou partielle).
     */
    public function update(Request $request, Signalement $signalement)
    {
        Gate::authorize('update', $signalement);

        $validated = $request->validate([
            'status' => 'sometimes|string',
            'texte' => 'sometimes|string',
        ]);

        $signalement->update($validated);

        return response()->json($signalement);
    }

    /**
     * Rechercher les signalements similaires.
     */
    public function similaires(Request $request)
    {
        $category = $request->query('category');

        $query = Signalement::query();

        if ($category) {
            $query->memeCategorie($category);
        }

        $signalements = $query->get();

        return response()->json($signalements);
    }
}
