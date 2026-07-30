<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Incident;
use App\Models\Signalement;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Agent Municipal can list incidents of their department.
     */
    public function test_agent_municipal_can_list_incidents_of_their_department(): void
    {
        $deptA = Departement::factory()->create(['nom' => 'Voirie']);
        $deptB = Departement::factory()->create(['nom' => 'Espaces Verts']);

        $agent = User::factory()->agentMunicipal($deptA)->create();

        $incidentA = Incident::create([
            'titre' => 'Nid de poule Rue 1',
            'department_id' => $deptA->id,
            'validated_by' => $agent->id,
        ]);

        $incidentB = Incident::create([
            'titre' => 'Arbre tombé Parc',
            'department_id' => $deptB->id,
            'validated_by' => $agent->id, // validation is not matching dept
        ]);

        $response = $this->actingAs($agent)->getJson('/api/incidents');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $incidentA->id);
    }

    /**
     * Citoyen cannot list incidents.
     */
    public function test_citoyen_cannot_list_incidents(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        $response = $this->actingAs($citoyen)->getJson('/api/incidents');

        $response->assertForbidden();
    }

    /**
     * Agent can create an incident in their department.
     */
    public function test_agent_municipal_can_create_incident(): void
    {
        $dept = Departement::factory()->create(['nom' => 'Voirie']);
        $agent = User::factory()->agentMunicipal($dept)->create();

        $response = $this->actingAs($agent)->postJson('/api/incidents', [
            'titre' => 'Nouvel Incident Majeur',
            'description' => 'Une description de test.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('titre', 'Nouvel Incident Majeur');
        $response->assertJsonPath('department_id', $dept->id);
        $response->assertJsonPath('validated_by', $agent->id);

        $this->assertDatabaseHas('incidents', [
            'titre' => 'Nouvel Incident Majeur',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);
    }

    /**
     * Agent can update their incident.
     */
    public function test_agent_municipal_can_update_incident(): void
    {
        $dept = Departement::factory()->create(['nom' => 'Voirie']);
        $agent = User::factory()->agentMunicipal($dept)->create();

        $incident = Incident::create([
            'titre' => 'Titre initial',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);

        $response = $this->actingAs($agent)->putJson("/api/incidents/{$incident->id}", [
            'titre' => 'Titre mis à jour',
        ]);

        $response->assertOk();
        $response->assertJsonPath('titre', 'Titre mis à jour');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'titre' => 'Titre mis à jour',
        ]);
    }

    /**
     * Agent cannot delete incident with attached signalements (asserts 409).
     */
    public function test_agent_cannot_delete_incident_with_attached_signalements(): void
    {
        $dept = Departement::factory()->create(['nom' => 'Voirie']);
        $agent = User::factory()->agentMunicipal($dept)->create();
        $citoyen = User::factory()->citoyen()->create();

        $incident = Incident::create([
            'titre' => 'Incident avec signalement',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);

        $signalement = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $dept->id,
            'incident_id' => $incident->id,
        ]);

        $response = $this->actingAs($agent)->deleteJson("/api/incidents/{$incident->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Impossible de supprimer un incident contenant encore des signalements rattachés.');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
        ]);
    }

    /**
     * Agent can delete incident without signalements.
     */
    public function test_agent_can_delete_empty_incident(): void
    {
        $dept = Departement::factory()->create(['nom' => 'Voirie']);
        $agent = User::factory()->agentMunicipal($dept)->create();

        $incident = Incident::create([
            'titre' => 'Incident vide',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);

        $response = $this->actingAs($agent)->deleteJson("/api/incidents/{$incident->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('incidents', [
            'id' => $incident->id,
        ]);
    }
}
