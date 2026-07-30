<?php

namespace App\Http\Controllers\Api;

use App\Enums\SignalementStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignalementRequest;
use App\Http\Requests\UpdateSignalementRequest;
use App\Http\Requests\UpdateSignalementStatusRequest;
use App\Models\Signalement;
use App\Services\AI\SignalementAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SignalementController extends Controller
{
    /**
     * Liste des signalements filtrée selon le rôle de l'utilisateur.
     * Citoyen = ses signalements | Agent = son département
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Signalement::query();

        if ($user) {
            if ($user->role === UserRole::Citoyen) {
                $query->where('user_id', $user->id);
            } elseif ($user->role === UserRole::AgentMunicipal) {
                $query->where('department_id', $user->department_id);
            }
        }

        if ($request->has('category')) {
            $query->memeCategorie($request->query('category'));
        }

        $signalements = $query->with(['user', 'departement', 'incident'])->latest()->get();

        return response()->json($signalements);
    }

    /**
     * Consulter un signalement individuel.
     */
    public function show(Signalement $signalement): JsonResponse
    {
        Gate::authorize('view', $signalement);

        return response()->json($signalement->load(['user', 'departement', 'incident']));
    }

    /**
     * Créer un nouveau signalement et lancer immédiatement l'analyse IA.
     */
    public function store(StoreSignalementRequest $request, SignalementAnalyzer $analyzer): JsonResponse
    {
        Gate::authorize('create', Signalement::class);

        $validated = $request->validated();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('signalements', 'public');
        }

        $signalement = Signalement::create([
            'user_id' => $request->user()->id,
            'texte' => $validated['texte'],
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'photo_path' => $photoPath,
            'status' => SignalementStatus::Nouveau->value,
            'ai_analysis_status' => 'en_attente',
        ]);

        // Appel direct du service SignalementAnalyzer (Lien US2 - US3)
        $signalement = $analyzer->analyze($signalement);

        return response()->json($signalement->load(['user', 'departement']), 201);
    }

    /**
     * Modifier un signalement (seul l'auteur et si le statut est encore 'nouveau').
     */
    public function update(UpdateSignalementRequest $request, Signalement $signalement): JsonResponse
    {
        Gate::authorize('update', $signalement);

        $status = $signalement->status;
        $statusValue = $status instanceof SignalementStatus ? $status->value : (string) $status;

        // Restriction de sécurité : Seul l'auteur peut modifier le texte et seulement si statut nouveau
        if ($request->user()->id === $signalement->user_id && $statusValue !== SignalementStatus::Nouveau->value) {
            return response()->json([
                'message' => 'Impossible de modifier un signalement dont le statut n\'est plus nouveau.'
            ], 403);
        }

        $validated = $request->validated();

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('signalements', 'public');
            unset($validated['photo']);
        }

        $signalement->update($validated);

        return response()->json($signalement->fresh(['user', 'departement']));
    }

    /**
     * Modifier le statut d'un signalement (Agent municipal du même département).
     */
    public function updateStatus(UpdateSignalementStatusRequest $request, Signalement $signalement): JsonResponse
    {
        Gate::authorize('updateStatus', $signalement);

        $validated = $request->validated();

        $signalement->update([
            'status' => $validated['status'],
        ]);

        return response()->json($signalement->fresh(['user', 'departement']));
    }

    /**
     * Rechercher les signalements similaires par catégorie.
     */
    public function similaires(Request $request): JsonResponse
    {
        $category = $request->query('category');

        $query = Signalement::query();

        if ($category) {
            $query->memeCategorie($category);
        }

        $signalements = $query->with(['user', 'departement'])->get();

        return response()->json($signalements);
    }
}
