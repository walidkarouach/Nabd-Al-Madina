<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Departement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Citoyen->value,
            'department_id' => null,
        ];
    }

    public function citoyen(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Citoyen->value,
            'department_id' => null,
        ]);
    }

    public function agentMunicipal(?Departement $departement = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AgentMunicipal->value,
            'department_id' => $departement?->id ?? Departement::factory(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin->value,
            'department_id' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
