<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Signalement;
use App\Services\AI\SignalementAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignalementAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Réponse IA valide : catégorie, priorité, résumé et urgence sont persistés
     * et ai_analysis_status passe à "succes".
     */
    public function test_analyse_persiste_les_donnees_quand_la_reponse_ia_est_valide(): void
    {
        $departement = Departement::factory()->create(['nom' => 'Éclairage public']);
        $signalement = Signalement::factory()->create([
            'texte' => 'Le lampadaire de la rue est cassé depuis trois jours.',
            'category' => null,
            'priority' => null,
            'urgency' => null,
            'summary' => null,
            'department_id' => null,
            'ai_analysis_status' => 'en_attente',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'Éclairage public',
                                'priority' => 'medium',
                                'urgency' => 3,
                                'summary' => 'Lampadaire hors service depuis trois jours.',
                                'department_id' => $departement->id,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('Éclairage public', $resultat->category);
        $this->assertSame('medium', $resultat->priority->value);
        $this->assertSame('Lampadaire hors service depuis trois jours.', $resultat->summary);
        $this->assertSame('succes', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'category' => 'Éclairage public',
            'priority' => 'medium',
            'summary' => 'Lampadaire hors service depuis trois jours.',
            'ai_analysis_status' => 'succes',
            'department_id' => $departement->id,
        ]);
    }

    /**
     * Réponse IA malformée (texte simple, sans JSON) : le service ne lève jamais
     * d'exception, le signalement reste en base, ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_quand_la_reponse_ia_est_du_texte_simple(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        Http::fake([
            '*' => Http::response('Désolé, je ne peux pas traiter cette demande.', 200),
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Réponse IA malformée (JSON invalide) : le service ne lève jamais d'exception,
     * le signalement reste en base, ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_quand_la_reponse_ia_est_un_json_invalide(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"category": "Voirie", "priority": "high", invalide',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Timeout / erreur réseau : le service ne fait jamais planter l'appelant,
     * conserve le signalement en base et met ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_en_cas_de_timeout_reseau(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Erreur réseau simulée via une séquence de réponses HTTP en échec
     * (ex : indisponibilité temporaire du service IA).
     */
    public function test_analyse_echoue_proprement_avec_une_sequence_de_reponses_en_erreur(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push('Service Unavailable', 503),
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }
}