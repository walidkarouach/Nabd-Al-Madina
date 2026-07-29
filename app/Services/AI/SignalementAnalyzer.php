<?php

namespace App\Services\AI;

use App\Enums\SignalementPriority;
use App\Models\Departement;
use App\Models\Signalement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignalementAnalyzer
{
    /**
     * Analyse un signalement via l'IA et met à jour ses données.
     */
    public function analyze(Signalement $signalement): Signalement
    {
        try {
            $prompt = $this->buildPrompt($signalement);

            $url = config('services.ai.url', 'https://api.openai.com/v1/chat/completions');
            $key = config('services.ai.key', '');
            $model = config('services.ai.model', 'gpt-4o-mini');

            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => "Bearer {$key}",
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant IA spécialisé dans l\'analyse des signalements d\'incidents urbains. Réponds uniquement en JSON valide sans aucun texte ni formatage markdown autour.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                Log::warning("AI API call failed for signalement {$signalement->id}: " . $response->body());
                return $this->markAsFailed($signalement);
            }

            $rawContent = $response->json('choices.0.message.content') ?? $response->body();
            $parsedData = $this->extractJson($rawContent);

            if ($parsedData === null || !$this->validateFields($parsedData)) {
                Log::warning("AI response parsing or field validation failed for signalement {$signalement->id}");
                return $this->markAsFailed($signalement);
            }

            return $this->persist($signalement, $parsedData);
        } catch (Throwable $e) {
            Log::error("Error analyzing signalement {$signalement->id}: " . $e->getMessage());
            return $this->markAsFailed($signalement);
        }
    }

    /**
     * Construit un prompt strict forçant une réponse en JSON pur.
     */
    public function buildPrompt(Signalement $signalement): string
    {
        $departements = Departement::all(['id', 'nom'])->map(function ($dept) {
            return "ID: {$dept->id} - Nom: {$dept->nom}";
        })->implode("\n");

        return <<<PROMPT
Analyse le signalement citoyen suivant et réponds STRICTEMENT avec un objet JSON pur sans aucun texte additionnel, explication ou balise markdown.

Signalement :
"{$signalement->texte}"

La réponse JSON doit contenir EXACTEMENT les 5 champs suivants :
1. "category": (string) La catégorie principale (ex: "Voirie", "Éclairage public", "Propreté", "Espaces verts", "Sécurité", "Assainissement").
2. "priority": (string) Niveau de priorité. Valeurs autorisées STRICTEMENT : "low", "medium", "high".
3. "urgency": (integer) Niveau d'urgence entre 1 et 5 inclusivement.
4. "summary": (string) Résumé concis de l'incident en 1 à 2 phrases max.
5. "department_id": (integer|null) L'ID du département compétent parmi la liste ci-dessous, ou null si aucun ne correspond.

Liste des départements disponibles :
{$departements}

Exemple de format attendu :
{
  "category": "Voirie",
  "priority": "high",
  "urgency": 4,
  "summary": "Nid de poule dangereux sur la chaussée.",
  "department_id": 1
}
PROMPT;
    }

    /**
     * Nettoie et extrait le JSON de la réponse texte de l'IA.
     */
    public function extractJson(string $rawContent): ?array
    {
        $content = trim($rawContent);

        // Supprimer les balises Markdown éventuelles ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Vérifie la présence des 5 champs requis dans le tableau extrait.
     */
    public function validateFields(array $data): bool
    {
        $hasCategory = !empty($data['category']);
        $hasPriority = isset($data['priority']);
        $hasUrgency = isset($data['urgency']);
        $hasSummary = !empty($data['summary']);
        $hasDepartment = array_key_exists('department_id', $data) || array_key_exists('department', $data) || array_key_exists('department_name', $data);

        return $hasCategory && $hasPriority && $hasUrgency && $hasSummary && $hasDepartment;
    }

    /**
     * Persiste les résultats de l'analyse avec revalidation stricte de chaque champ.
     */
    public function persist(Signalement $signalement, array $data): Signalement
    {
        // Validation et assainissement de priority
        $priorityValue = strtolower(trim((string) ($data['priority'] ?? 'medium')));
        if (!in_array($priorityValue, ['low', 'medium', 'high'], true)) {
            $priorityValue = 'medium';
        }

        // Urgency clampé strictement entre 1 et 5
        $urgencyValue = (int) ($data['urgency'] ?? 3);
        $urgencyValue = max(1, min(5, $urgencyValue));

        // Résolution du department_id
        $departmentId = null;
        if (!empty($data['department_id']) && is_numeric($data['department_id'])) {
            $deptId = (int) $data['department_id'];
            if (Departement::where('id', $deptId)->exists()) {
                $departmentId = $deptId;
            }
        }

        if ($departmentId === null && !empty($data['department'])) {
            $deptName = (string) $data['department'];
            $foundDept = Departement::where('nom', 'LIKE', "%{$deptName}%")->first();
            if ($foundDept) {
                $departmentId = $foundDept->id;
            }
        }

        $signalement->update([
            'category' => (string) ($data['category'] ?? 'Général'),
            'priority' => $priorityValue,
            'urgency' => $urgencyValue,
            'summary' => (string) ($data['summary'] ?? ''),
            'department_id' => $departmentId,
            'ai_analysis_status' => 'succes',
        ]);

        return $signalement->fresh();
    }

    /**
     * Marque l'analyse comme échouée sans bloquer ni perdre le signalement.
     */
    public function markAsFailed(Signalement $signalement): Signalement
    {
        $signalement->update([
            'ai_analysis_status' => 'echec',
        ]);

        return $signalement->fresh();
    }
}
