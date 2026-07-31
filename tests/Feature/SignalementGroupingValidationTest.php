<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Incident;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalementGroupingValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teste que les citoyens ne peuvent pas valider un regroupement (403).
     */
    public function test_citoyen_cannot_validate_grouping(): void
    {
        $citoyen = User::factory()->citoyen()->create();
        $signalement = Signalement::factory()->create();

        $response = $this->actingAs($citoyen)->postJson("/api/signalements/{$signalement->id}/valider-regroupement", [
            'titre' => 'Nouvel incident citoyen',
        ]);

        $response->assertForbidden();
    }

    /**
     * Teste qu'un agent d'un autre département ne peut pas agir sur un signalement d'un département différent.
     */
    public function test_agent_from_different_department_cannot_validate_grouping(): void
    {
        $deptA = Departement::factory()->create();
        $deptB = Departement::factory()->create();

        $agentA = User::factory()->agentMunicipal($deptA)->create();
        $signalementB = Signalement::factory()->create([
            'department_id' => $deptB->id,
        ]);

        $response = $this->actingAs($agentA)->postJson("/api/signalements/{$signalementB->id}/valider-regroupement", [
            'titre' => 'Nouvel incident',
        ]);

        $response->assertForbidden();
    }

    /**
     * Teste qu'un agent d'un département ne peut pas rattacher à un incident appartenant à un autre département.
     */
    public function test_agent_cannot_attach_to_incident_of_different_department(): void
    {
        $deptA = Departement::factory()->create();
        $deptB = Departement::factory()->create();

        $agentA = User::factory()->agentMunicipal($deptA)->create();
        $signalementA = Signalement::factory()->create([
            'department_id' => $deptA->id,
        ]);

        $incidentB = Incident::create([
            'titre' => 'Incident Département B',
            'department_id' => $deptB->id,
        ]);

        $response = $this->actingAs($agentA)->postJson("/api/signalements/{$signalementA->id}/valider-regroupement", [
            'incident_id' => $incidentB->id,
        ]);

        $response->assertForbidden();
    }

    /**
     * Teste la validation des champs (titre requis si incident_id absent).
     */
    public function test_title_is_required_without_incident_id(): void
    {
        $dept = Departement::factory()->create();
        $agent = User::factory()->agentMunicipal($dept)->create();
        $signalement = Signalement::factory()->create([
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAs($agent)->postJson("/api/signalements/{$signalement->id}/valider-regroupement", [
            // titre absent
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['titre']);
    }

    /**
     * Teste la validation et création d'un nouvel incident avec succès.
     */
    public function test_validate_grouping_creates_new_incident_successfully(): void
    {
        $dept = Departement::factory()->create();
        $agent = User::factory()->agentMunicipal($dept)->create();
        
        $citoyen = User::factory()->citoyen()->create();

        $signalement1 = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $dept->id,
        ]);

        $signalement2 = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $dept->id,
        ]);

        // Ce signalement appartient à un autre département
        $deptAutre = Departement::factory()->create();
        $signalementAutre = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $deptAutre->id,
        ]);

        $response = $this->actingAs($agent)->postJson("/api/signalements/{$signalement1->id}/valider-regroupement", [
            'titre' => 'Nouvel incident de test',
            'description' => 'Description de test',
            'signalement_ids' => [$signalement2->id, $signalementAutre->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Regroupement validé avec succès.');
        $response->assertJsonStructure([
            'message',
            'incident' => [
                'id',
                'titre',
                'description',
                'department_id',
                'validated_by',
                'signalements',
            ]
        ]);

        $incidentId = $response->json('incident.id');

        // L'incident a bien été créé
        $this->assertDatabaseHas('incidents', [
            'id' => $incidentId,
            'titre' => 'Nouvel incident de test',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);

        // Le signalement courant et le signalement2 (même département) sont associés
        $this->assertEquals($incidentId, $signalement1->fresh()->incident_id);
        $this->assertEquals($incidentId, $signalement2->fresh()->incident_id);

        // Le signalement d'un autre département n'est pas associé
        $this->assertNull($signalementAutre->fresh()->incident_id);
    }

    /**
     * Teste l'association à un incident existant avec succès.
     */
    public function test_validate_grouping_attaches_to_existing_incident_successfully(): void
    {
        $dept = Departement::factory()->create();
        $agent = User::factory()->agentMunicipal($dept)->create();
        
        $citoyen = User::factory()->citoyen()->create();

        $incident = Incident::create([
            'titre' => 'Incident existant',
            'department_id' => $dept->id,
            'validated_by' => $agent->id,
        ]);

        $signalement1 = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $dept->id,
        ]);

        $signalement2 = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAs($agent)->postJson("/api/signalements/{$signalement1->id}/valider-regroupement", [
            'incident_id' => $incident->id,
            'signalement_ids' => [$signalement2->id],
        ]);

        $response->assertOk();
        $this->assertEquals($incident->id, $signalement1->fresh()->incident_id);
        $this->assertEquals($incident->id, $signalement2->fresh()->incident_id);
    }
}
