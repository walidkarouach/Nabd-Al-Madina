<?php

namespace App\Policies;

use App\Enums\UserRole;
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
        if ($user->role === UserRole::Citoyen) {
            return $user->id === $signalement->user_id;
        }

        if ($user->role === UserRole::AgentMunicipal) {
            return $user->department_id !== null
                && $user->department_id === $signalement->department_id;
        }

        return false;
    }

    /**
     * Tout utilisateur authentifié peut créer un signalement.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Seul un agent municipal du même département peut modifier
     * un signalement.
     */
    public function update(User $user, Signalement $signalement): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id !== null
            && $user->department_id === $signalement->department_id;
    }

    /**
     * Seul un agent municipal du même département peut supprimer
     * un signalement.
     */
    public function delete(User $user, Signalement $signalement): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id !== null
            && $user->department_id === $signalement->department_id;
    }
}