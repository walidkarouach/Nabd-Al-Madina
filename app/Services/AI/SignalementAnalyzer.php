<?php

namespace App\Services\AI;

use App\Ai\Agents\SignalementClassifier;
use App\Models\Departement;
use App\Models\Signalement;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignalementAnalyzer
{
    /**
     * Analyse un signalement avec Laravel AI SDK.
     */
    public function analyze(Signalement $signalement): Signalement
    {
        try {
            $departements = Departement::query()
                ->get(['id', 'nom'])
                ->map(fn ($departement) => [
                    'id' => $departement->id,
                    'nom' => $departement->nom,
                ])
                ->values()
                ->toArray();

            $prompt = $this->buildPrompt(
                $signalement,
                $departements
            );

            $result = app(SignalementClassifier::class)->prompt($prompt);

            if (is_object($result) && property_exists($result, 'structured') && is_array($result->structured)) {
                $raw = $result->structured;
            } elseif (is_array($result) || $result instanceof \ArrayAccess) {
                $raw = $result;
            } elseif (is_object($result) && method_exists($result, 'toArray')) {
                $raw = $result->toArray();
            } else {
                Log::warning(
                    "AI response is not valid for signalement {$signalement->id}"
                );

                return $this->markAsFailed($signalement);
            }

            $data = [
                'category' => $raw['category'] ?? null,
                'priority' => $raw['priority'] ?? null,
                'urgency' => $raw['urgency'] ?? null,
                'summary' => $raw['summary'] ?? null,
                'department_id' => $raw['department_id'] ?? null,
                'department' => $raw['department'] ?? null,
            ];

            if (!$this->validateFields($data)) {
                Log::warning(
                    "AI response validation failed for signalement {$signalement->id}",
                    ['data' => $data]
                );

                return $this->markAsFailed($signalement);
            }

            return $this->persist($signalement, $data);

        } catch (Throwable $e) {

            Log::error(
                "Error analyzing signalement {$signalement->id}: {$e->getMessage()}",
                [
                    'exception' => get_class($e),
                ]
            );

            return $this->markAsFailed($signalement);
        }
    }

    /**
     * Construit le prompt envoyé à l'agent IA.
     */
    public function buildPrompt(
        Signalement $signalement,
        array $departements
    ): string {

        $departementsText = collect($departements)
            ->map(fn ($departement) =>
                "ID: {$departement['id']} - Nom: {$departement['nom']}"
            )
            ->implode("\n");

        return <<<PROMPT
Analyse le signalement citoyen suivant.

Signalement :
"{$signalement->texte}"

Voici la liste des départements existants :

{$departementsText}

Retourne une classification structurée.

Contraintes importantes :

1. category :
Catégorie principale du problème.
Exemples :
- Voirie
- Éclairage public
- Propreté
- Espaces verts
- Sécurité
- Eau
- Accessibilité
- Assainissement

2. priority :
Uniquement :
- low
- medium
- high

3. urgency :
Nombre entier entre 1 et 5.

4. summary :
Résumé court du problème.

5. department_id :
Choisis uniquement un ID parmi les départements fournis ci-dessus.
Si aucun département ne correspond, retourne null.

Ne crée jamais un nouvel ID.
PROMPT;
    }

    /**
     * Valide les données retournées par l'IA.
     */
    public function validateFields(array $data): bool
    {
        if (empty($data['category'])) {
            return false;
        }

        if (!in_array(
            $data['priority'] ?? null,
            ['low', 'medium', 'high'],
            true
        )) {
            return false;
        }

        if (!isset($data['urgency'])) {
            return false;
        }

        if (
            !is_int($data['urgency']) ||
            $data['urgency'] < 1 ||
            $data['urgency'] > 5
        ) {
            return false;
        }

        if (empty($data['summary'])) {
            return false;
        }

        return true;
    }

    /**
     * Sauvegarde le résultat de l'analyse.
     */
    public function persist(
        Signalement $signalement,
        array $data
    ): Signalement {

        $departmentId = null;

        if (
            !empty($data['department_id']) &&
            is_numeric($data['department_id'])
        ) {
            $departmentId = (int) $data['department_id'];

            if (!Departement::where('id', $departmentId)->exists()) {
                $departmentId = null;
            }
        } elseif (!empty($data['department']) && is_string($data['department'])) {
            $deptName = trim($data['department']);
            $departement = Departement::firstOrCreate(['nom' => $deptName]);
            $departmentId = $departement->id;
        } elseif (!empty($data['department_id']) && is_string($data['department_id'])) {
            $deptName = trim($data['department_id']);
            $departement = Departement::firstOrCreate(['nom' => $deptName]);
            $departmentId = $departement->id;
        }

        $signalement->update([
            'category' => $data['category'],
            'priority' => $data['priority'],
            'urgency' => $data['urgency'],
            'summary' => $data['summary'],
            'department_id' => $departmentId,
            'ai_analysis_status' => 'succes',
        ]);

        return $signalement->fresh();
    }

    /**
     * Marque l'analyse comme échouée.
     */
    public function markAsFailed(
        Signalement $signalement
    ): Signalement {

        $signalement->update([
            'ai_analysis_status' => 'echec',
        ]);

        return $signalement->fresh();
    }
}