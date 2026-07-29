<?php

namespace Tests\Feature;

use App\Enums\SignalementStatus;
use App\Models\Departement;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalementPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Citoyen A tente de consulter un signalement appartenant au Citoyen B -> HTTP 403.
     */
    public function test_citoyen_a_cannot_view_signalement_of_citoyen_b(): void
    {
        $citoyenA = User::factory()->citoyen()->create();
        $citoyenB = User::factory()->citoyen()->create();

        $signalement = Signalement::factory()->create([
            'user_id' => $citoyenB->id,
        ]);

        $response = $this->actingAs($citoyenA)
            ->getJson("/api/signalements/{$signalement->id}");

        $response->assertForbidden();
    }

    /**
     * Test 2: Agent A tente de modifier le statut d'un signalement du Département B -> HTTP 403.
     */
    public function test_agent_a_cannot_update_status_of_signalement_in_department_b(): void
    {
        $departementA = Departement::factory()->create();
        $departementB = Departement::factory()->create();

        $agentA = User::factory()->agentMunicipal($departementA)->create();
        $agentB = User::factory()->agentMunicipal($departementB)->create();

        $signalement = Signalement::factory()->create([
            'department_id' => $departementB->id,
        ]);

        $response = $this->actingAs($agentA)
            ->patchJson("/api/signalements/{$signalement->id}/status", [
                'status' => SignalementStatus::EnCours->value,
            ]);

        $response->assertForbidden();
    }

    /**
     * Test 3: Un citoyen propriétaire de son signalement tente lui-même de changer son statut -> HTTP 403.
     */
    public function test_citoyen_owner_cannot_update_signalement_status(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        $signalement = Signalement::factory()->create([
            'user_id' => $citoyen->id,
        ]);

        $response = $this->actingAs($citoyen)
            ->patchJson("/api/signalements/{$signalement->id}/status", [
                'status' => SignalementStatus::EnCours->value,
            ]);

        $response->assertForbidden();
    }

    /**
     * Test 4: Un agent du même département modifie le statut -> HTTP 200 + assertDatabaseHas.
     */
    public function test_agent_can_update_signalement_status_in_same_department(): void
    {
        $departement = Departement::factory()->create();
        $agent = User::factory()->agentMunicipal($departement)->create();

        $signalement = Signalement::factory()->create([
            'department_id' => $departement->id,
            'status' => SignalementStatus::Nouveau->value,
        ]);

        $response = $this->actingAs($agent)
            ->patchJson("/api/signalements/{$signalement->id}/status", [
                'status' => SignalementStatus::EnCours->value,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'status' => SignalementStatus::EnCours->value,
        ]);
    }
}
