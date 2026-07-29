<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    /**
     * Seul un agent municipal peut voir un incident.
     */
    public function view(User $user, Incident $incident): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id !== null
            && $user->department_id === $incident->department_id;
    }

    /**
     * Seul un agent municipal peut créer un incident.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::AgentMunicipal;
    }

    /**
     * Seul un agent du même département peut modifier un incident.
     */
    public function update(User $user, Incident $incident): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id !== null
            && $user->department_id === $incident->department_id;
    }

    /**
     * Un incident ne peut être supprimé que s'il ne contient aucun signalement.
     */
    public function delete(User $user, Incident $incident): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id === $incident->department_id
            && $incident->signalements()->count() === 0;
    }

    /**
     * Validation du regroupement des signalements.
     */
    public function validateGrouping(User $user, Incident $incident): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id === $incident->department_id;
    }
}