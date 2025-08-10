# Plugin MLF - Sessions de jeu

Plugin WordPress pour gérer les sessions de jeu et les inscriptions des joueurs.

## Fonctionnalités

- Gestion des sessions de jeu avec différents types (murder, escape game, etc.)
- Système d'inscription des joueurs avec confirmation
- Interface d'administration complète
- Gestion des images pour les sessions
- Formulaires personnalisés spécifiques à chaque session de jeu
- Collecte des réponses des joueurs aux formulaires

## Installation

1. Uploadez le dossier `mlf` dans le répertoire `/wp-content/plugins/`
2. Activez le plugin depuis l'interface d'administration WordPress
3. Accédez au menu "Sessions MLF" pour commencer à gérer vos sessions

## Structure de la base de données

- `wp_mlf_game_sessions` : Sessions de jeu
- `wp_mlf_player_registrations` : Inscriptions des joueurs
- `wp_mlf_custom_forms` : Formulaires personnalisés spécifiques à chaque session
- `wp_mlf_custom_form_responses` : Réponses aux formulaires personnalisés

## Développement

Pour contribuer au développement de ce plugin, vous pouvez :

1. Cloner le repository
2. Installer les dépendances de développement
3. Suivre les standards de codage WordPress