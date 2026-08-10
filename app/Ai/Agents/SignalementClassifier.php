<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;


class SignalementClassifier implements Agent, HasStructuredOutput
{
    use Promptable;

    public function provider(): Lab|string
    {
        return Lab::Groq;
    }

    public function model(): string
    {
        return env('GROQ_MODEL', 'openai/gpt-oss-120b');
    }

    public function instructions(): string
    {
        return <<<PROMPT
Tu es une IA spécialisée dans l'analyse des signalements urbains.

Tu dois analyser le texte d'un signalement citoyen et retourner uniquement
les informations structurées demandées.

Règles :
- category doit être une catégorie urbaine pertinente.
- priority doit être uniquement : low, medium ou high.
- urgency doit être un entier entre 1 et 5.
- summary doit être court et précis.
- department_id doit correspondre à un département existant fourni dans le prompt.
- Ne crée jamais un nouvel ID de département.
- Si aucun département ne correspond, retourne null.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema
                ->string()
                ->required(),

            'priority' => $schema
                ->string()
                ->enum(['low', 'medium', 'high'])
                ->required(),

            'urgency' => $schema
                ->integer()
                ->min(1)
                ->max(5)
                ->required(),

            'summary' => $schema
                ->string()
                ->required(),

            'department_id' => $schema
                ->integer()
                ->nullable()
                ->required(),
        ];
    }
}