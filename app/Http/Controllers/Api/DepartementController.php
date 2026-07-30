<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartementResource;
use App\Models\Departement;
use Illuminate\Http\Request;

class DepartementController extends Controller
{
    public function index()
    {
        return DepartementResource::collection(
            Departement::all()
        );
    }

    public function show(Departement $departement)
    {
        return new DepartementResource($departement);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:departements,nom',
        ]);

        $departement = Departement::create($validated);

        return new DepartementResource($departement);
    }

    public function update(Request $request, Departement $departement)
    {
        $validated = $request->validate([
            'nom' => 'required|string|unique:departements,nom,' . $departement->id,
        ]);

        $departement->update($validated);

        return new DepartementResource($departement);
    }

    public function destroy(Departement $departement)
    {
        if ($departement->signalements()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce département.'
            ], 422);
        }

        $departement->delete();

        return response()->json([
            'message' => 'Département supprimé avec succès.'
        ]);
    }
}