<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Incident;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignalementCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un citoyen peut créer un signalement en texte libre : le POST déclenche
     * la création en base ET l'analyse IA (simulée via Http::fake) en une seule requête,
     * qui enrichit automatiquement le signalement (catégorie, priorité, résumé).
     */
    public function test_un_citoyen_peut_creer_un_signalement_en_texte_libre(): void
    {
        $departement = Departement::factory()->create(['nom' => 'Voirie']);
        $citoyen = User::factory()->citoyen()->create();

        // Simule complètement l'appel IA sans effectuer de requête réseau réelle.
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Voirie',
                                'priority' => 'high',
                                'urgency' => 4,
                                'summary' => 'Nid de poule dangereux signalé par un citoyen.',
                                'department_id' => $departement->id,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'texte' => 'Il y a un énorme nid de poule dangereux avenue Mohammed V.',
            'lat' => 33.5731,
            'lng' => -7.5898,
        ];

        // Une seule requête HTTP : création + analyse IA + enrichissement automatique.
        $response = $this->actingAs($citoyen)->postJson('/api/signalements', $payload);

        $response->assertCreated();

        // Le signalement existe et est correctement enrichi par l'IA.
        $response->assertJsonPath('category', 'Voirie');
        $response->assertJsonPath('priority', 'high');
        $response->assertJsonPath('summary', 'Nid de poule dangereux signalé par un citoyen.');
        $response->assertJsonPath('ai_analysis_status', 'succes');

        $this->assertDatabaseHas('signalements', [
            'user_id' => $citoyen->id,
            'texte' => $payload['texte'],
            'category' => 'Voirie',
            'priority' => 'high',
            'summary' => 'Nid de poule dangereux signalé par un citoyen.',
            'ai_analysis_status' => 'succes',
            'department_id' => $departement->id,
        ]);

        // Un seul appel HTTP a été effectué vers le service IA.
        Http::assertSentCount(1);
    }

    /**
     * La suppression d'un incident encore rattaché à au moins un signalement
     * est refusée (règle d'intégrité référentielle -> HTTP 409).
     */
    public function test_suppression_d_un_incident_non_vide_est_refusee(): void
    {
        $departement = Departement::factory()->create(['nom' => 'Voirie']);
        $agent = User::factory()->agentMunicipal($departement)->create();
        $citoyen = User::factory()->citoyen()->create();

        $incident = Incident::create([
            'titre' => 'Incident regroupant plusieurs signalements',
            'department_id' => $departement->id,
            'validated_by' => $agent->id,
        ]);

        Signalement::factory()->create([
            'user_id' => $citoyen->id,
            'department_id' => $departement->id,
            'incident_id' => $incident->id,
        ]);

        $response = $this->actingAs($agent)->deleteJson("/api/incidents/{$incident->id}");

        $response->assertStatus(409);

        // L'incident est toujours présent en base, il n'a pas été supprimé.
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
        ]);
    }
}