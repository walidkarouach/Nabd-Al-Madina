<?php

namespace App\Services\AI;

use App\Models\Signalement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignalementSimilarityService
{
    /**
     * Recherche et évalue les signalements similaires pour un signalement donné.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findCandidates(Signalement $signalement): array
    {
        $candidates = $this->selectCandidates($signalement);

        if ($candidates->isEmpty()) {
            return [];
        }

        try {
            $prompt = $this->buildPrompt($signalement, $candidates);

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
                            'content' => 'Tu es un assistant d\'analyse de similarité de signalements urbains. Réponds uniquement en JSON valide sans balises Markdown.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content') ?? $response->body();
                $parsed = $this->extractJson($content);

                if (is_array($parsed)) {
                    return $this->mergeAiResults($candidates, $parsed);
                }
            }

            Log::warning("AI similarity request failed or returned invalid JSON for signalement {$signalement->id}");
        } catch (Throwable $e) {
            Log::error("Error in SignalementSimilarityService for signalement {$signalement->id}: " . $e->getMessage());
        }

        // Fallback en cas d'échec de l'IA : score basé sur la proximité géographique
        return $this->fallbackSpatialResults($candidates);
    }

    /**
     * Sélectionne le sous-ensemble pertinent de candidats :
     * Non résolus (scopeOuvert), même catégorie (scopeMemeCategorie) et < 300m.
     */
    public function selectCandidates(Signalement $signalement): Collection
    {
        $query = Signalement::query()
            ->ouvert()
            ->where('id', '!=', $signalement->id);

        if (!empty($signalement->category)) {
            $query->memeCategorie($signalement->category);
        }

        $allOpen = $query->get();

        return $allOpen->filter(function (Signalement $candidate) use ($signalement) {
            $distKm = $this->distanceKm(
                (float) $signalement->lat,
                (float) $signalement->lng,
                (float) $candidate->lat,
                (float) $candidate->lng
            );

            $distMeters = round($distKm * 1000, 2);

            if ($distMeters <= 300.0) {
                $candidate->distance_m = $distMeters;
                return true;
            }

            return false;
        })->values();
    }

    /**
     * Calcule la distance entre deux points géographiques (latitude/longitude) en kilomètres.
     * Formule d'Haversine.
     */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Construit le prompt structuré pour l'évaluation par l'IA.
     */
    public function buildPrompt(Signalement $target, Collection $candidates): string
    {
        $candidatesList = $candidates->map(function (Signalement $c) {
            return [
                'id' => $c->id,
                'texte' => $c->texte,
                'summary' => $c->summary,
                'distance_m' => $c->distance_m ?? null,
            ];
        })->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Compare le signalement cible suivant avec la liste des candidats géographiquement proches (< 300m) et de même catégorie.

Signalement Cible (ID: {$target->id}) :
Texte: "{$target->texte}"
Résumé: "{$target->summary}"
Catégorie: "{$target->category}"

Liste des Candidats :
{$candidatesList}

Pour chaque candidat, évalue le niveau de similarité et indique si c'est un doublon.
Réponds STRICTEMENT sous la forme d'un tableau JSON d'objets sans texte autour :
[
  {
    "candidate_id": <int>,
    "similarity_score": <float entre 0.0 et 1.0>,
    "verdict": <string: "doublon_certain" | "doublon_probable" | "non_similaire">,
    "reasoning": <string explicative courte>
  }
]
PROMPT;
    }

    /**
     * Extrait le JSON de la réponse de l'IA.
     */
    public function extractJson(string $rawContent): ?array
    {
        $content = trim($rawContent);

        if (preg_match('/```(?:json)?\s*(\[.*?\]|\{.*?\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/\[.*\]/s', $content, $matches)) {
            $content = $matches[0];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Fusionne les résultats de l'IA avec la collection de candidats.
     */
    protected function mergeAiResults(Collection $candidates, array $aiResults): array
    {
        $aiMap = [];
        foreach ($aiResults as $res) {
            if (isset($res['candidate_id'])) {
                $aiMap[$res['candidate_id']] = $res;
            }
        }

        $results = [];
        foreach ($candidates as $candidate) {
            $aiData = $aiMap[$candidate->id] ?? null;

            $score = isset($aiData['similarity_score']) ? (float) $aiData['similarity_score'] : 0.5;
            $verdict = $aiData['verdict'] ?? ($score >= 0.7 ? 'doublon_probable' : 'non_similaire');
            $reasoning = $aiData['reasoning'] ?? 'Évalué par l\'IA.';

            $results[] = [
                'signalement' => $candidate,
                'similarity_score' => max(0.0, min(1.0, $score)),
                'verdict' => $verdict,
                'reasoning' => $reasoning,
                'distance_m' => $candidate->distance_m ?? null,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return $results;
    }

    /**
     * Fallback spatiale si l'IA échoue.
     */
    protected function fallbackSpatialResults(Collection $candidates): array
    {
        $results = [];
        foreach ($candidates as $candidate) {
            $distM = $candidate->distance_m ?? 150.0;
            // Plus c'est proche, plus le score spatial est élevé
            $score = max(0.1, round(1.0 - ($distM / 300.0), 2));

            $results[] = [
                'signalement' => $candidate,
                'similarity_score' => $score,
                'verdict' => $score >= 0.7 ? 'doublon_probable' : 'non_similaire',
                'reasoning' => 'Évalué par proximité géographique (Fallback IA).',
                'distance_m' => $distM,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return $results;
    }
}
