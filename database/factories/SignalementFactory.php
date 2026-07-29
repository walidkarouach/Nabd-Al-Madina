<?php

namespace Database\Factories;

use App\Enums\SignalementPriority;
use App\Enums\SignalementStatus;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SignalementFactory extends Factory
{
    protected $model = Signalement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'texte' => fake()->paragraph(),
            'lat' => fake()->latitude(31, 35),
            'lng' => fake()->longitude(-9, -6),
            'photo_path' => null,
            'category' => 'Voirie',
            'priority' => fake()->randomElement([SignalementPriority::Low->value, SignalementPriority::Medium->value, SignalementPriority::High->value]),
            'urgency' => fake()->numberBetween(1, 5),
            'summary' => fake()->sentence(),
            'ai_analysis_status' => 'succes',
            'department_id' => null,
            'incident_id' => null,
            'status' => SignalementStatus::Nouveau->value,
        ];
    }
}
