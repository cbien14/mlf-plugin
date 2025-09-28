# Documentation des Shortcodes MLF

Ce document liste tous les shortcodes disponibles dans le plugin MLF (Murder/Gaming Sessions) pour l'intégration dans les pages et articles WordPress.

## 📋 Liste complète des shortcodes

### 🎭 1. Liste des sessions - `[mlf_sessions_list]`

**Fonction :** Affiche la liste des sessions Murder disponibles avec navigation automatique

**Paramètres :**
- `limit` (défaut: 10) - Nombre maximum de sessions à afficher
- `upcoming_only` (défaut: true) - Afficher uniquement les sessions à venir

**Fonctionnalités :**
- Navigation automatique vers les détails (URL : `?action=details&session_id=123`)
- Navigation automatique vers l'inscription (URL : `?action=register&session_id=123`)
- Affichage responsive avec cartes de session
- Filtrage par date automatique si `upcoming_only=true`

**Exemples d'utilisation :**
```markdown
[mlf_sessions_list]
[mlf_sessions_list limit="5"]
[mlf_sessions_list upcoming_only="false" limit="20"]
```

---

### 🔍 2. Détails d'une session - `[mlf_session_details]`

**Fonction :** Affiche les détails complets d'une session spécifique

**Paramètres :**
- `session_id` (requis) - ID de la session à afficher

**Affichage inclus :**
- Informations complètes de la session
- Synopsis et avertissements
- Outils de sécurité
- Prérequis
- Informations sur le meneur
- Boutons d'action (inscription, etc.)

**Exemple d'utilisation :**
```markdown
[mlf_session_details session_id="123"]
```

---

### 📝 3. Formulaire d'inscription - `[mlf_registration_form]`

**Fonction :** Affiche le formulaire d'inscription à une session

**Paramètres :**
- `session_id` (requis) - ID de la session pour l'inscription

**Fonctionnalités :**
- Vérification automatique de la connexion utilisateur
- Gestion des formulaires personnalisés de session
- Messages d'erreur et de validation
- Redirection automatique après inscription

**Prérequis :** Utilisateur connecté

**Exemple d'utilisation :**
```markdown
[mlf_registration_form session_id="123"]
```

---

### 🎨 4. Proposition de session - `[mlf_propose_session]`

**Fonction :** Permet aux utilisateurs connectés de proposer de nouvelles sessions Murder

**Paramètres :** Aucun paramètre requis

**Fonctionnalités :**
- Formulaire complet de création de session
- Validation automatique des données
- Soumission en attente de validation par un administrateur
- Notification par email à l'utilisateur et aux administrateurs
- Interface responsive et conviviale

**Champs du formulaire :**
- Nom de l'organisateur (pré-rempli)
- Informations de base (nom, date, heure, durée)
- Nombre de joueurs (min/max)
- Lieu de la session
- Synopsis et note d'intention
- Avertissements sur le contenu
- Outils de sécurité et prérequis
- Images (bannière et fond)
- Date limite d'inscription

**Prérequis :** Utilisateur connecté

**Statuts des sessions :**
- `en_attente` : Session soumise, en attente de validation
- `planifiee` : Session approuvée et visible publiquement
- `rejetee` : Session rejetée (supprimée avec notification)

**Exemple d'utilisation :**
```markdown
[mlf_propose_session]
```

---

### 📊 5. Sessions de l'utilisateur - `[mlf_user_sessions]`

**Fonction :** Affiche les sessions auxquelles l'utilisateur connecté est inscrit

**Paramètres :**
- `show_past` (défaut: true) - Afficher les sessions passées
- `show_upcoming` (défaut: true) - Afficher les sessions à venir
- `limit` (défaut: 20) - Nombre maximum de sessions à afficher

**Affichage inclus :**
- Grille de cartes de sessions
- Statut d'inscription
- Liens vers les détails
- Formulaires personnalisés si disponibles

**Prérequis :** Utilisateur connecté

**Exemples d'utilisation :**
```markdown
[mlf_user_sessions]
[mlf_user_sessions show_past="false"]
[mlf_user_sessions limit="10" show_upcoming="true"]
```

---

### 👤 6. Profil utilisateur - `[mlf_user_profile]`

**Fonction :** Affiche le profil et les statistiques de l'utilisateur connecté

**Paramètres :** Aucun paramètre requis

**Affichage inclus :**
- Informations de profil
- Statistiques de participation
- Historique des sessions
- Données de performance

**Prérequis :** Utilisateur connecté

**Exemple d'utilisation :**
```markdown
[mlf_user_profile]
```

---

### 📑 7. Fiches de personnage - `[mlf_character_sheets]`

**Fonction :** Affiche les fiches de personnage d'une session

**Paramètres :**
- `session_id` (requis) - ID de la session
- `user_id` (optionnel) - ID de l'utilisateur (par défaut: utilisateur connecté)
- `mode` (défaut: "view") - Mode d'affichage : "view" ou "manage"

**Modes disponibles :**
- `view` : Affichage en lecture seule
- `manage` : Gestion et modification des fiches

**Fonctionnalités :**
- Vérification automatique des permissions
- Affichage conditionnel selon le mode
- Interface de gestion pour les administrateurs

**Exemples d'utilisation :**
```markdown
[mlf_character_sheets session_id="123"]
[mlf_character_sheets session_id="123" mode="manage"]
[mlf_character_sheets session_id="123" user_id="456"]
```

---

## 🎯 Cas d'utilisation recommandés

### Page "Sessions disponibles"
Affiche toutes les sessions à venir avec possibilité de navigation :
```markdown
[mlf_sessions_list limit="10" upcoming_only="true"]
```

### Page "Mon compte utilisateur"
Combine profil et sessions personnelles :
```markdown
[mlf_user_profile]

[mlf_user_sessions limit="15"]
```

### Page "Proposer une session"
Permet aux utilisateurs de soumettre leurs propres sessions :
```markdown
[mlf_propose_session]
```

### Page dédiée à une session spécifique
Page complète pour une session avec détails et inscription :
```markdown
[mlf_session_details session_id="123"]

[mlf_registration_form session_id="123"]
```

### Page "Archive des sessions"
Toutes les sessions (passées et futures) :
```markdown
[mlf_sessions_list upcoming_only="false" limit="50"]
```

### Page de gestion des fiches (pour administrateurs)
```markdown
[mlf_character_sheets session_id="123" mode="manage"]
```

---

## ⚠️ Notes importantes

### Authentification requise
Les shortcodes suivants nécessitent qu'un utilisateur soit connecté :
- `[mlf_user_sessions]`
- `[mlf_user_profile]` 
- `[mlf_registration_form]`
- `[mlf_propose_session]` 🆕

### Gestion des permissions
- `[mlf_character_sheets]` vérifie automatiquement les permissions selon le mode
- Les utilisateurs non autorisés voient un message d'erreur approprié

### Navigation automatique
- `[mlf_sessions_list]` gère automatiquement la navigation via paramètres URL :
  - `?action=details&session_id=123` pour les détails
  - `?action=register&session_id=123` pour l'inscription

### Classes CSS disponibles
Tous les shortcodes utilisent des classes CSS standardisées :
- `.mlf-sessions-list` - Container principal des listes
- `.mlf-session-card` - Cartes individuelles de session
- `.mlf-user-sessions` - Container des sessions utilisateur
- `.mlf-user-profile` - Container du profil utilisateur
- `.mlf-character-sheets` - Container des fiches de personnage
- `.mlf-btn` - Boutons d'action
- `.mlf-error` - Messages d'erreur
- `.mlf-login-required` - Messages de connexion requise
- `.mlf-propose-session-container` - Container du formulaire de proposition 🆕
- `.mlf-status-badge` - Badges de statut des sessions 🆕

### Gestion des propositions de sessions 🆕
Le système de propositions permet aux utilisateurs de soumettre leurs sessions :

**Workflow de validation :**
1. Utilisateur soumet une session via `[mlf_propose_session]`
2. Session créée avec statut `en_attente`
3. Administrateurs notifiés par email
4. Interface admin pour approuver/rejeter (via `Admin > Sessions MLF`)
5. Notification à l'utilisateur du résultat

**Interface administrateur :**
- Filtre "En attente" dans la liste des sessions
- Badge avec compteur de sessions en attente
- Boutons "Approuver" et "Rejeter" 
- Possibilité d'ajouter un motif de rejet

**Statuts des sessions :**
- `en_attente` : En attente de validation (invisible publiquement)
- `planifiee` : Approuvée et visible
- `en_cours` : Session en cours
- `terminee` : Session terminée  
- `annulee` : Session annulée

### Responsive Design
Tous les shortcodes sont conçus pour être responsive et s'adaptent automatiquement à tous les types d'écrans.

---

## 🔧 Développement et personnalisation

### Ajout de nouveaux shortcodes
Les shortcodes sont définis dans les fichiers suivants :
- `mlf/public/class-mlf-frontend.php` - Shortcodes publics
- `mlf/includes/class-mlf-user-account.php` - Shortcodes utilisateur
- `mlf/includes/class-mlf-character-sheets.php` - Shortcodes fiches de personnage

### Personnalisation des styles
Les styles CSS se trouvent dans :
- `mlf/public/css/mlf-public.css`
- `mlf/public/css/mlf-game-events.css`
- `mlf/public/css/mlf-custom-forms.css`

### Scripts JavaScript
Les interactions JavaScript sont dans :
- `mlf/public/js/mlf-public.js`
- `mlf/public/js/mlf-game-events.js`
- `mlf/public/js/mlf-custom-forms.js`

---

*Documentation générée le 28 septembre 2025*
*Plugin MLF version 1.3.0*