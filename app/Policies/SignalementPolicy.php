<?php

namespace App\Policies;

use App\Enums\SignalementStatus;
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

        if ($user->role === UserRole::Admin) {
            return true;
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
     * Seul l'auteur (si le statut est encore 'nouveau') ou un agent municipal du même département
     * peut modifier un signalement.
     */
    public function update(User $user, Signalement $signalement): bool
    {
        if ($user->role === UserRole::AgentMunicipal) {
            return $user->department_id !== null
                && $user->department_id === $signalement->department_id;
        }

        if ($user->role === UserRole::Citoyen || $user->id === $signalement->user_id) {
            $statusValue = $signalement->status instanceof SignalementStatus
                ? $signalement->status->value
                : (string) $signalement->status;

            return $user->id === $signalement->user_id && $statusValue === SignalementStatus::Nouveau->value;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return false;
    }

    /**
     * Seul un agent municipal du même département peut modifier le statut d'un signalement.
     */
    public function updateStatus(User $user, Signalement $signalement): bool
    {
        return $user->role === UserRole::AgentMunicipal
            && $user->department_id !== null
            && $user->department_id === $signalement->department_id;
    }

    /**
     * Seul un agent municipal du même département (ou admin) peut rechercher les signalements similaires.
     */
    public function viewSimilaires(User $user, Signalement $signalement): bool
    {
        if ($user->role === UserRole::AgentMunicipal) {
            return $signalement->department_id === null
                || ($user->department_id !== null && $user->department_id === $signalement->department_id);
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return false;
    }

    /**
     * Seul un agent municipal du même département peut supprimer un signalement.
     */
    public function delete(User $user, Signalement $signalement): bool
    {
        if ($user->role === UserRole::AgentMunicipal) {
            return $user->department_id !== null
                && $user->department_id === $signalement->department_id;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return false;
    }
}