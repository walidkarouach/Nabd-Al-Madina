<?php

namespace Tests\Feature;

use App\Enums\SignalementStatus;
use App\Models\Departement;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NadaBoutaTasksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * StoreSignalementRequest valide la création avec texte 10-2000 car, lat/lng obligatoires/bornés.
     */
    public function test_store_signalement_validation_fails_on_invalid_data(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        $response = $this->actingAs($citoyen)->postJson('/api/signalements', [
            'texte' => 'Court', // < 10 caractères
            'lat' => 100, // hors bornes [-90, 90]
            'lng' => 200, // hors bornes [-180, 180]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['texte', 'lat', 'lng']);
    }

    /**
     * Store signalement crée en base et appelle immédiatement le service SignalementAnalyzer (IA Succès).
     */
    public function test_store_signalement_creates_and_analyzes_successfully(): void
    {
        $departement = Departement::factory()->create(['nom' => 'Voirie']);
        $citoyen = User::factory()->citoyen()->create();

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Voirie',
                                'priority' => 'high',
                                'urgency' => 4,
                                'summary' => 'Grand trou dangereux dans la rue principale.',
                                'department_id' => $departement->id,
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $payload = [
            'texte' => 'Un énorme nid de poule s\'est formé sur l\'avenue Hassan II.',
            'lat' => 33.5731,
            'lng' => -7.5898,
        ];

        $response = $this->actingAs($citoyen)->postJson('/api/signalements', $payload);

        $response->assertCreated();
        $response->assertJsonPath('ai_analysis_status', 'succes');
        $response->assertJsonPath('category', 'Voirie');
        $response->assertJsonPath('priority', 'high');
        $response->assertJsonPath('urgency', 4);
        $response->assertJsonPath('department_id', $departement->id);

        $this->assertDatabaseHas('signalements', [
            'user_id' => $citoyen->id,
            'texte' => $payload['texte'],
            'ai_analysis_status' => 'succes',
            'department_id' => $departement->id,
        ]);
    }

    /**
     * En cas d'erreur de l'IA (Timeout ou JSON invalide), markAsFailed() est appelé sans perdre le signalement.
     */
    public function test_store_signalement_handles_ai_failure_gracefully(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        // Simulation d'une erreur serveur / timeout 500
        Http::fake([
            '*' => Http::response('AI Service Unavailable', 500)
        ]);

        $payload = [
            'texte' => 'Fuite d\'eau très importante au niveau du trottoir.',
            'lat' => 33.5800,
            'lng' => -7.6000,
        ];

        $response = $this->actingAs($citoyen)->postJson('/api/signalements', $payload);

        $response->assertCreated();
        $response->assertJsonPath('ai_analysis_status', 'echec');

        $this->assertDatabaseHas('signalements', [
            'user_id' => $citoyen->id,
            'texte' => $payload['texte'],
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Index filtre selon le rôle (citoyen = ses signalements, agent = son département).
     */
    public function test_index_filters_by_user_role(): void
    {
        $deptA = Departement::factory()->create();
        $deptB = Departement::factory()->create();

        $citoyenA = User::factory()->citoyen()->create();
        $citoyenB = User::factory()->citoyen()->create();

        $agentA = User::factory()->agentMunicipal($deptA)->create();

        $sigCitoyenA = Signalement::factory()->create([
            'user_id' => $citoyenA->id,
            'department_id' => $deptA->id,
        ]);

        $sigCitoyenB = Signalement::factory()->create([
            'user_id' => $citoyenB->id,
            'department_id' => $deptB->id,
        ]);

        // Citoyen A ne voit que ses signalements
        $resCitoyen = $this->actingAs($citoyenA)->getJson('/api/signalements');
        $resCitoyen->assertOk();
        $resCitoyen->assertJsonCount(1);
        $resCitoyen->assertJsonPath('0.id', $sigCitoyenA->id);

        // Agent A ne voit que les signalements de son département (deptA)
        $resAgent = $this->actingAs($agentA)->getJson('/api/signalements');
        $resAgent->assertOk();
        $resAgent->assertJsonCount(1);
        $resAgent->assertJsonPath('0.id', $sigCitoyenA->id);
    }

    /**
     * Seul l'auteur peut modifier le texte, et seulement si le statut est encore nouveau.
     */
    public function test_author_can_update_text_only_when_status_is_nouveau(): void
    {
        $citoyen = User::factory()->citoyen()->create();
        $autreCitoyen = User::factory()->citoyen()->create();

        $signalementNouveau = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'status' => SignalementStatus::Nouveau->value,
            'texte' => 'Ancien texte valide du signalement',
        ]);

        // Auteur modifie son signalement statut nouveau -> 200 OK
        $resOk = $this->actingAs($citoyen)->patchJson("/api/signalements/{$signalementNouveau->id}", [
            'texte' => 'Nouveau texte mis à jour par l\'auteur.',
        ]);
        $resOk->assertOk();
        $this->assertDatabaseHas('signalements', [
            'id' => $signalementNouveau->id,
            'texte' => 'Nouveau texte mis à jour par l\'auteur.',
        ]);

        // Autre citoyen essaie de modifier -> 403 Forbidden
        $resForbidden = $this->actingAs($autreCitoyen)->patchJson("/api/signalements/{$signalementNouveau->id}", [
            'texte' => 'Modification non autorisée par un tiers.',
        ]);
        $resForbidden->assertForbidden();

        // Signalement passé en_cours -> Auteur ne peut plus modifier -> 403 Forbidden
        $signalementEnCours = Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'status' => SignalementStatus::EnCours->value,
            'texte' => 'Texte de départ quand en cours',
        ]);

        $resBlocked = $this->actingAs($citoyen)->patchJson("/api/signalements/{$signalementEnCours->id}", [
            'texte' => 'Essai de mise à jour quand statut en cours.',
        ]);
        $resBlocked->assertForbidden();
    }

    /**
     * AI response missing required fields makes analysis fail.
     */
    public function test_ai_analysis_fails_when_required_fields_are_missing(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Voirie',
                                'priority' => 'high',
                                // Missing urgency, summary, and department
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $payload = [
            'texte' => 'Un énorme nid de poule s\'est formé sur l\'avenue Hassan II.',
            'lat' => 33.5731,
            'lng' => -7.5898,
        ];

        $response = $this->actingAs($citoyen)->postJson('/api/signalements', $payload);

        $response->assertCreated();
        $response->assertJsonPath('ai_analysis_status', 'echec');
    }

    /**
     * AI analysis creates a new department when name is not found in database.
     */
    public function test_ai_analysis_creates_new_department_when_not_found_in_database(): void
    {
        $citoyen = User::factory()->citoyen()->create();

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Éclairage public',
                                'priority' => 'medium',
                                'urgency' => 3,
                                'summary' => 'Lampadaire en panne dans le quartier.',
                                'department' => 'Eclairage Specifique', // Department doesn't exist yet
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $payload = [
            'texte' => 'Le lampadaire de ma rue ne s\'allume plus depuis hier soir.',
            'lat' => 33.5731,
            'lng' => -7.5898,
        ];

        // Ensure department doesn't exist
        $this->assertDatabaseMissing('departements', ['nom' => 'Eclairage Specifique']);

        $response = $this->actingAs($citoyen)->postJson('/api/signalements', $payload);

        $response->assertCreated();
        $response->assertJsonPath('ai_analysis_status', 'succes');
        $response->assertJsonPath('category', 'Éclairage public');

        // It should have created the new department and linked it
        $this->assertDatabaseHas('departements', ['nom' => 'Eclairage Specifique']);
        $newDept = Departement::where('nom', 'Eclairage Specifique')->first();
        $this->assertNotNull($newDept);

        $response->assertJsonPath('department_id', $newDept->id);
    }
}
