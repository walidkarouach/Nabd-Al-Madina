<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignalementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $isAgent = $request->user()?->role === UserRole::AgentMunicipal;

        return [
            'id' => $this->id,
            'texte' => $this->texte,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'photo_path' => $this->photo_path,

            'category' => $this->category,
            'priority' => $this->priority?->value,
            'urgency' => $this->urgency,
            'summary' => $this->summary,
            'status' => $this->status?->value,

            'departement' => $this->departement?->nom,
            'incident_id' => $this->incident_id,

            // يظهر فقط للـAgent
            'auteur' => $isAgent ? $this->user?->name : null,

            'created_at' => $this->created_at,
        ];
    }
}