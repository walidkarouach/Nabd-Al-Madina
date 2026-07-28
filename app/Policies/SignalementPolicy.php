<?php

namespace App\Policies;

use App\Models\Signalement;
use App\Models\User;

class SignalementPolicy
{
    /**
     * Un citoyen ne peut voir que ses propres signalements.
     * Un agent municipal ne peut voir que les signalements de son département.
     */
    public function view(User $user, Signalement $signalement): bool
    {
        if ($user->role === 'citoyen') {
            return $user->id === $signalement->user_id;
        }

        if ($user->role === 'agent_municipal') {
            return $user->department_id !== null
                && $user->department_id === $signalement->department_id;
        }

        return false;
    }

    /**
     * Tout utilisateur authentifié (citoyen) peut créer un signalement.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Seul un agent municipal (de son département) peut modifier
     * un signalement (statut, department, rattachement incident).
     */
    public function update(User $user, Signalement $signalement): bool
    {
        return $user->role === 'agent_municipal'
            && $user->department_id !== null
            && $user->department_id === $signalement->department_id;
    }

    /**
     * Suppression réservée à l'agent de son département (à ajuster
     * selon règles métier si besoin de restrictions supplémentaires).
     */
    public function delete(User $user, Signalement $signalement): bool
    {
        return $user->role === 'agent_municipal'
            && $user->department_id !== null
            && $user->department_id === $signalement->department_id;
    }
}