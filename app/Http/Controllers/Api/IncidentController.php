<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidentController extends Controller
{
    /**
     * Liste des incidents de la direction/département de l'agent.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== UserRole::AgentMunicipal) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $incidents = Incident::where('department_id', $user->department_id)
            ->with(['signalements', 'departement', 'validateur'])
            ->latest()
            ->get();

        return response()->json($incidents);
    }

    /**
     * Consulter un incident individuel.
     */
    public function show(Incident $incident): JsonResponse
    {
        Gate::authorize('view', $incident);

        return response()->json($incident->load(['signalements', 'departement', 'validateur']));
    }

    /**
     * Créer un nouvel incident (rattaché au département de l'agent municipal).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Incident::class);

        $validated = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $incident = Incident::create([
            'titre' => $validated['titre'],
            'description' => $validated['description'] ?? null,
            'department_id' => $request->user()->department_id,
            'validated_by' => $request->user()->id,
        ]);

        return response()->json($incident->load(['departement', 'validateur']), 201);
    }

    /**
     * Modifier un incident.
     */
    public function update(Request $request, Incident $incident): JsonResponse
    {
        Gate::authorize('update', $incident);

        $validated = $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $incident->update($validated);

        return response()->json($incident->load(['departement', 'validateur']));
    }

    /**
     * Supprimer un incident avec intégrité référentielle (règle n°10).
     */
    public function destroy(Request $request, Incident $incident): JsonResponse
    {
        $user = $request->user();
        
        // Sécurité de base en amont pour éviter de divulguer la présence de signalements d'autres départements
        if ($user->role !== UserRole::AgentMunicipal || $user->department_id !== $incident->department_id) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        // Règle de gestion n°10 — intégrité référentielle (Erreur 409 explicite)
        if ($incident->signalements()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un incident contenant encore des signalements rattachés.'
            ], 409);
        }

        Gate::authorize('delete', $incident);

        $incident->delete();

        return response()->json(null, 204);
    }
}
