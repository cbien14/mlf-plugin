# Implémentation des formulaires (Session Forms)

Ce document décrit comment les formulaires personnalisés par session sont définis, stockés et consommés dans le plugin. Le code est en anglais; ce guide utilise les noms de classes/colonnes exacts.

## Vue d’ensemble

- Gestionnaire principal: `MLF_Session_Forms_Manager`
- Tables:
	- `wp_mlf_custom_forms` (définition par session)
	- `wp_mlf_custom_form_responses` (réponses des joueurs, avec `user_id`)
- Intégration:
	- Admin: page « Formulaire de session » (via `MLF_Admin`) pour créer/éditer
	- Frontend: affichage et collecte dans `MLF_Frontend` (page d’inscription et mise à jour des réponses)

## Schéma base de données

### Table `wp_mlf_custom_forms`
- `id` (PK)
- `session_id` (unique): référence vers `wp_mlf_game_sessions.id`
- `form_title` varchar(255)
- `form_description` text
- `form_fields` longtext JSON (voir format ci-dessous)
- `is_active` tinyint(1)
- `created_at`, `updated_at`

Contrainte: `UNIQUE KEY unique_session_form (session_id)`

### Table `wp_mlf_custom_form_responses`
- `id` (PK)
- `session_id` (FK)
- `registration_id` (FK vers `wp_mlf_player_registrations.id`)
- `user_id` int(11) NOT NULL (ajouté en v1.1.0)
- `response_data` longtext JSON
- `submitted_at` datetime

Contrainte: `UNIQUE KEY unique_response (session_id, registration_id)`

## Format JSON `form_fields`

`form_fields` est un tableau d’objets. Chaque champ suit ce contrat minimal:

- `type` (string): `text` | `textarea` | `select`
- `name` (string): identifiant HTML; si absent, généré `field_{index}`
- `label` (string): libellé affiché
- `required` (bool): optionnel (défaut: false)
- `placeholder` (string): optionnel (auto-ajouté pour `text`/`textarea` si manquant)
- `options` (array|string): requis pour `select` (tableau de valeurs; une chaîne multi-lignes est acceptée et sera normalisée en tableau)

Exemple:

```
[
	{
		"type": "text",
		"name": "character_concept",
		"label": "Concept de personnage souhaité",
		"placeholder": "Ex: Guerrier nain...",
		"required": false
	},
	{
		"type": "select",
		"name": "experience_level",
		"label": "Votre expérience",
		"required": true,
		"options": ["novice", "debutant", "intermediaire", "experimente"]
	}
]
```

## Nettoyage/validation JSON

La méthode `clean_and_decode_json()` est robuste aux encodages « sales »:
- Supprime des doubles échappements (`\\` → `\`, `\"` → `"`)
- Retire des quotes externes superflues
- Fallback: `stripslashes()` puis `json_decode`
- Si échec: retourne des champs par défaut via `get_default_form_fields()`
- Tous les champs sont ensuite validés/sanitisés (`sanitize_text_field`, trimming, normalisation d’`options`)

La méthode `validate_and_clean_form_data()` applique les mêmes règles lors de la sauvegarde côté admin.

## Champs par défaut (par type de jeu)

Fournis par `get_default_form_fields($game_type)`:
- `murder`: `character_preference` (text), `experience_level` (select), `special_requests` (textarea)
- `jdr`: `character_concept` (text), `experience_level` (select)
- `jeu_de_societe`: `complexity_preference` (select), `game_preferences` (textarea)

Ces valeurs sont utilisées en fallback quand un JSON invalide est détecté ou lors d’une création initiale.

## Sauvegarde des réponses

`save_form_response($session_id, $registration_id, $response_data)`:
- Récupère `user_id` via `mlf_player_registrations`
- Insère ou met à jour la ligne unique `(session_id, registration_id)`
- Encode `response_data` via `wp_json_encode`

Récupération:
- `get_form_response($session_id, $registration_id)`
- `get_session_responses($session_id)` (jointure pour inclure `player_name`, `player_email`)

## Intégration Frontend

- `MLF_Frontend::handle_update_custom_responses_direct()` (POST direct) et `wp_ajax_mlf_update_custom_responses` (AJAX)
- Contrôles:
	- utilisateur connecté
	- inscription existante et correspondante (`get_user_registration`)
	- statut d’inscription `registration_status === 'confirme'`

## Administration

- Page « Formulaire de session » (menu admin) pour créer/éditer le formulaire d’une session:
	- Utilise `validate_and_clean_form_data()`
	- Stockage dans `wp_mlf_custom_forms`
	- Un seul formulaire actif par session (`is_active = 1`)

## Bonnes pratiques & limites

- Toujours échapper les sorties dans les vues (`esc_html`, `wp_kses_post`)
- Les noms de champ `name` doivent rester stables pour éviter la perte de données côté réponses
- Pour les `select`, préférer des options stables (clés/valeurs explicites) si on étend le modèle
- Taille des payloads: `longtext` est large mais éviter des champs/redondances inutiles

## Évolutions suggérées

- Ajouter la notion de `version` au formulaire pour tracer les évolutions par session
- Support d’autres types (`checkbox`, `radio`, `date`) avec normalisation stricte
- Exposer une petite API REST WP (authentifiée) pour gérer formulaires et réponses

