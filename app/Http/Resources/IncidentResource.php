<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,

            'departement' => $this->departement?->nom,

            'validated_by' => $this->validateur?->name,

            'signalements_count' => $this->signalements()->count(),

            'created_at' => $this->created_at,
        ];
    }
}