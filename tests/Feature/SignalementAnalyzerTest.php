<?php

namespace Tests\Feature;

use App\Ai\Agents\SignalementClassifier;
use App\Models\Departement;
use App\Models\Signalement;
use App\Services\AI\SignalementAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

        SignalementClassifier::fake([
            [
                'category' => 'Éclairage public',
                'priority' => 'medium',
                'urgency' => 3,
                'summary' => 'Lampadaire hors service depuis trois jours.',
                'department_id' => $departement->id,
            ],
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
            'urgency' => 3,
            'summary' => 'Lampadaire hors service depuis trois jours.',
            'ai_analysis_status' => 'succes',
            'department_id' => $departement->id,
        ]);
    }

    /**
     * Réponse IA malformée / non-array : le service ne lève jamais d'exception,
     * ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_quand_la_reponse_ia_est_du_texte_simple(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake(['Texte brut sans tableau JSON']);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Provider exception : le service ne lève jamais d'exception,
     * le signalement reste en base, ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_sur_exception_du_provider(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            function () {
                throw new \RuntimeException('AI provider error');
            },
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Timeout / ConnectionException : le service ne fait jamais planter l'appelant,
     * conserve le signalement en base et met ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_proprement_en_cas_de_timeout_reseau(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            function () {
                throw new ConnectionException('Connection timed out.');
            },
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'ai_analysis_status' => 'echec',
        ]);
    }

    /**
     * Réponse structurée incomplète (manque champs requis comme urgency/summary) -> ai_analysis_status = "echec".
     */
    public function test_analyse_echoue_quand_la_reponse_est_incomplete(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            [
                'category' => 'Voirie',
                'priority' => 'high',
                // urgency & summary absents
            ],
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('echec', $resultat->ai_analysis_status);
    }

    /**
     * department_id invalide (n'existe pas en DB) -> department_id devient null, ai_analysis_status = "succes".
     */
    public function test_analyse_gere_department_id_invalide(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            [
                'category' => 'Voirie',
                'priority' => 'high',
                'urgency' => 4,
                'summary' => 'Nid de poule sur la chaussée',
                'department_id' => 99999, // Inexistant en base
            ],
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('succes', $resultat->ai_analysis_status);
        $this->assertNull($resultat->department_id);
    }

    /**
     * department_id null -> department_id reste null, ai_analysis_status = "succes".
     */
    public function test_analyse_gere_department_id_null(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            [
                'category' => 'Propreté',
                'priority' => 'low',
                'urgency' => 1,
                'summary' => 'Dépôt sauvage d\'ordures',
                'department_id' => null,
            ],
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('succes', $resultat->ai_analysis_status);
        $this->assertNull($resultat->department_id);
    }

    /**
     * Création d'un département si un nom de département (string) est fourni par l'IA.
     */
    public function test_analyse_cree_departement_si_nom_fourni(): void
    {
        $signalement = Signalement::factory()->create([
            'ai_analysis_status' => 'en_attente',
        ]);

        SignalementClassifier::fake([
            [
                'category' => 'Environnement',
                'priority' => 'medium',
                'urgency' => 3,
                'summary' => 'Nuisance sonore constante',
                'department' => 'Environnement et Bruit',
            ],
        ]);

        $resultat = (new SignalementAnalyzer())->analyze($signalement);

        $this->assertSame('succes', $resultat->ai_analysis_status);
        $this->assertNotNull($resultat->department_id);

        $this->assertDatabaseHas('departements', [
            'nom' => 'Environnement et Bruit',
        ]);
    }
}