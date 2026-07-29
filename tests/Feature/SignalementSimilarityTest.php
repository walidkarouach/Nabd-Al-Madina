<?php

namespace Tests\Feature;

use App\Enums\SignalementStatus;
use App\Models\Departement;
use App\Models\Signalement;
use App\Models\User;
use App\Services\AI\SignalementSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignalementSimilarityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teste la formule Haversine pour le calcul de distance.
     */
    public function test_haversine_distance_calculation(): void
    {
        $service = new SignalementSimilarityService();

        // Points à Casablanca séparés de ~50 mètres
        $lat1 = 33.573100;
        $lng1 = -7.589800;

        $lat2 = 33.573500;
        $lng2 = -7.589500;

        $distKm = $service->distanceKm($lat1, $lng1, $lat2, $lng2);
        $distM = $distKm * 1000;

        // La distance doit être inférieure à 100 mètres et supérieure à 10 mètres
        $this->assertGreaterThan(10, $distM);
        $this->assertLessThan(100, $distM);
    }

    /**
     * Teste la sélection des candidats (selectCandidates) :
     * Filtre les résolus/rejetés, les catégories différentes et > 300m.
     */
    public function test_select_candidates_filters_correctly(): void
    {
        $service = new SignalementSimilarityService();

        // Signalement cible (Voirie, 33.5731, -7.5898)
        $target = Signalement::factory()->create([
            'category' => 'Voirie',
            'status' => SignalementStatus::Nouveau->value,
            'lat' => 33.573100,
            'lng' => -7.589800,
        ]);

        // Candidat 1 : Valide (< 300m, même catégorie, ouvert) -> ~50m
        $validCandidate = Signalement::factory()->create([
            'category' => 'Voirie',
            'status' => SignalementStatus::EnCours->value,
            'lat' => 33.573400,
            'lng' => -7.589600,
        ]);

        // Candidat 2 : Rejeté (Catégorie différente "Éclairage")
        $diffCategory = Signalement::factory()->create([
            'category' => 'Éclairage public',
            'status' => SignalementStatus::Nouveau->value,
            'lat' => 33.573200,
            'lng' => -7.589700,
        ]);

        // Candidat 3 : Rejeté (Statut 'resolu')
        $resolvedCandidate = Signalement::factory()->create([
            'category' => 'Voirie',
            'status' => SignalementStatus::Resolu->value,
            'lat' => 33.573200,
            'lng' => -7.589700,
        ]);

        // Candidat 4 : Rejeté (> 300m -> ~2 km)
        $farCandidate = Signalement::factory()->create([
            'category' => 'Voirie',
            'status' => SignalementStatus::Nouveau->value,
            'lat' => 33.590000,
            'lng' => -7.610000,
        ]);

        $candidates = $service->selectCandidates($target);

        $this->assertCount(1, $candidates);
        $this->assertEquals($validCandidate->id, $candidates->first()->id);
        $this->assertLessThanOrEqual(300, $candidates->first()->distance_m);
    }

    /**
     * Citoyen est bloqué à l'accès de l'endpoint (403 via middleware role:agent_municipal).
     */
    public function test_citoyen_is_forbidden_from_similaires_endpoint(): void
    {
        $citoyen = User::factory()->citoyen()->create();
        $signalement = Signalement::factory()->create();

        $response = $this->actingAs($citoyen)->getJson("/api/signalements/{$signalement->id}/similaires");

        $response->assertForbidden();
    }

    /**
     * Agent d'un autre département est bloqué (403 via Policy viewSimilaires).
     */
    public function test_agent_from_different_department_is_forbidden(): void
    {
        $deptA = Departement::factory()->create();
        $deptB = Departement::factory()->create();

        $agentA = User::factory()->agentMunicipal($deptA)->create();
        $signalementDeptB = Signalement::factory()->create([
            'department_id' => $deptB->id,
        ]);

        $response = $this->actingAs($agentA)->getJson("/api/signalements/{$signalementDeptB->id}/similaires");

        $response->assertForbidden();
    }

    /**
     * Agent municipal du même département peut consulter les signalements similaires avec succès.
     */
    public function test_agent_in_same_department_can_get_similaires(): void
    {
        $dept = Departement::factory()->create();
        $agent = User::factory()->agentMunicipal($dept)->create();

        $target = Signalement::factory()->create([
            'department_id' => $dept->id,
            'category' => 'Voirie',
            'texte' => 'Nid de poule géant au milieu de la rue.',
            'lat' => 33.573100,
            'lng' => -7.589800,
        ]);

        $candidate = Signalement::factory()->create([
            'department_id' => $dept->id,
            'category' => 'Voirie',
            'status' => SignalementStatus::Nouveau->value,
            'texte' => 'Trou dangeureux sur la chaussée.',
            'lat' => 33.573300,
            'lng' => -7.589700,
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                [
                                    'candidate_id' => $candidate->id,
                                    'similarity_score' => 0.92,
                                    'verdict' => 'doublon_probable',
                                    'reasoning' => 'Signalement quasiment identique à 30m.',
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($agent)->getJson("/api/signalements/{$target->id}/similaires");

        $response->assertOk();
        $response->assertJsonPath('signalement_cible_id', $target->id);
        $response->assertJsonPath('nombre_candidats', 1);
        $response->assertJsonPath('similaires.0.similarity_score', 0.92);
        $response->assertJsonPath('similaires.0.verdict', 'doublon_probable');
    }
}
