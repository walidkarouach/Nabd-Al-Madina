# Nabd El Madina API

## Description

**Nabd El Madina** est une API REST développée avec Laravel permettant aux citoyens de signaler des problèmes urbains (lampadaire cassé, fuite d'eau, déchets, etc.).

L'objectif du projet est de centraliser les signalements, d'utiliser une Intelligence Artificielle pour les classifier automatiquement, détecter les doublons et permettre aux agents municipaux de gérer efficacement les incidents.

---

# Fonctionnalités

## Authentification

* Inscription des citoyens
* Connexion
* Déconnexion
* Authentification avec Laravel Sanctum
* Gestion des rôles (Citoyen / Agent municipal)

---

## Gestion des signalements

* Créer un signalement
* Consulter ses signalements
* Modifier un signalement (si son statut est "nouveau")
* Ajouter une photo (optionnelle)
* Enregistrer la localisation (latitude et longitude)

Après la création, l'IA analyse automatiquement le signalement et retourne :

* Catégorie
* Priorité
* Niveau d'urgence
* Résumé
* Département concerné

---

## Gestion des incidents

* Créer un incident
* Consulter les incidents
* Associer plusieurs signalements à un incident
* Supprimer uniquement un incident vide

---

## Gestion des départements

* Voirie
* Éclairage
* Eau
* Espaces verts
* Accessibilité

Chaque département peut contenir plusieurs agents et plusieurs signalements.

---

## Détection des doublons

* Recherche des signalements proches
* Comparaison des catégories
* Analyse par Intelligence Artificielle
* Proposition de regroupement
* Validation obligatoire par un agent municipal

---

## Sécurité

* Authentification avec Laravel Sanctum
* Middleware de rôle
* Policies
* API Resources
* Form Requests

Les règles appliquées :

* Un citoyen consulte uniquement ses propres signalements.
* Un agent consulte uniquement les signalements de son département.
* Seul un agent peut modifier le statut d'un signalement.
* Seul un agent peut valider un regroupement.
* Les données des citoyens sont protégées.

---

# Architecture du projet

* app/

  * Http/

    * Controllers/
    * Requests/
    * Resources/
  * Models/
  * Policies/
  * Services/

    * AI/

      * SignalementAnalyzer.php
      * SignalementSimilarityService.php
  * Enums/

* database/

  * migrations/
  * factories/
  * seeders/

---

# Relations entre les entités

* Un User possède plusieurs Signalements.
* Un User appartient à un Département.
* Un Département possède plusieurs Users.
* Un Département possède plusieurs Signalements.
* Un Département possède plusieurs Incidents.
* Un Incident possède plusieurs Signalements.
* Un Signalement appartient à un User.
* Un Signalement appartient éventuellement à un Incident.
* Un Signalement appartient à un Département.

---

# Installation

## 1. Cloner le projet

* git clone https://github.com/walidkarouach/Nabd-Al-Madina.git

## 2. Entrer dans le projet

* cd Nabd-El-Madina

## 3. Installer les dépendances

* composer install

## 4. Copier le fichier .env

* cp .env.example .env

## 5. Générer la clé Laravel

* php artisan key:generate

## 6. Configurer la base de données

Modifier le fichier **.env** :

* DB_CONNECTION=mysql
* DB_HOST=127.0.0.1
* DB_PORT=3306
* DB_DATABASE=nabd_el_madina
* DB_USERNAME=root
* DB_PASSWORD=

## 7. Configurer la clé IA

Dans le fichier **.env** :

* AI_API_KEY=your_api_key
* AI_MODEL=gpt-5.5

Puis configurer le fichier :

* config/services.php

## 8. Exécuter les migrations

* php artisan migrate

ou

* php artisan migrate:fresh

## 9. Insérer les données de test

* php artisan db:seed

ou

* php artisan migrate:fresh --seed

## 10. Lancer le serveur

* php artisan serve

---

# Authentification

Toutes les routes protégées utilisent un Bearer Token.

* Authorization: Bearer YOUR_TOKEN

---

# Endpoints principaux

## Authentification

* POST /api/register
* POST /api/login
* POST /api/logout
* GET /api/me

---

## Signalements

* GET /api/signalements
* POST /api/signalements
* GET /api/signalements/{id}
* PUT /api/signalements/{id}
* PATCH /api/signalements/{id}/status
* GET /api/signalements/{id}/similaires

---

## Incidents

* GET /api/incidents
* POST /api/incidents
* DELETE /api/incidents/{id}

---

## Validation des regroupements

* POST /api/signalements/{id}/validate-grouping

---

## Départements

* GET /api/departements
* POST /api/departements
* PUT /api/departements/{id}
* DELETE /api/departements/{id}

---

# Tests

Lancer tous les tests :

* php artisan test

Tests disponibles :

* SignalementCrudTest
* SignalementAnalyzerTest
* SignalementPolicyTest
* IncidentGroupingTest

---

# Démonstration

Le scénario de démonstration est le suivant :

* Un citoyen crée un signalement.
* L'IA analyse automatiquement le texte.
* Les informations retournées sont enregistrées.
* Le système recherche des signalements similaires.
* Un agent consulte les propositions de regroupement.
* L'agent valide ou refuse le regroupement.
* Le statut du signalement évolue jusqu'à sa résolution.

---

# Équipe

* Nada Bouta : Conception, CRUD Signalement, Services IA et Similarité.
* Oualid Karouach : Migrations, Authentification Sanctum et CRUD Incident.
* Dounia El Ghazi : Modèles Eloquent, Policies, API Resources et Gestion des erreurs IA.
* Chaymae Moukani : Factories, Seeders, Tests et Documentation.

---

# Technologies utilisées

* Laravel 13
* PHP 8.3
* MySQL
* Laravel Sanctum
* Laravel AI
* Eloquent ORM
* API Resources
* Form Requests
* PHPUnit
* Postman

---

# Licence

Projet réalisé dans le cadre d'une formation Backend Laravel.
