# MLF Plugin - Configuration des Tests de Régression

## 🎯 Objectif
S'assurer qu'aucune modification du plugin MLF n'introduit de régression dans les fonctionnalités existantes.

## 📋 Tests automatiques

### 1. Tests critiques (toujours exécutés)
- ✅ Structure de base de données
- ✅ Syntaxe PHP de tous les fichiers
- ✅ Requêtes admin sans erreur
- ✅ Classes frontend instanciables
- ✅ Système de migration fonctionnel

### 2. Tests fonctionnels
- ✅ Création de session
- ✅ Inscription utilisateur
- ✅ Formulaires customisés
- ✅ Sauvegarde des réponses
- ✅ Interface admin

### 3. Tests de sécurité
- ✅ Protection SQL injection
- ✅ Intégrité des données
- ✅ Permissions WordPress

## 🛠️ Scripts disponibles

### Tests rapides (< 30 secondes)
```bash
curl -s "http://localhost:8082/mlf-unit-tests.php"
```

### Tests complets (< 2 minutes)  
```bash
curl -s "http://localhost:8082/mlf-regression-tests.php"
```

### Déploiement sécurisé avec tests
```bash
./deploy-with-regression-tests.sh
```

### Health check de la base de données
```bash
curl -s "http://localhost:8082/mlf-database-health-check.php"
```

## 🚨 Procédure en cas d'échec

### Si un test échoue :
1. **STOP** - Ne pas déployer
2. Analyser l'erreur rapportée
3. Corriger le problème
4. Re-exécuter les tests
5. Déployer seulement si tous les tests passent

### Rollback si nécessaire :
```bash
# Restaurer depuis la sauvegarde
docker exec wordpress-dev_wordpress_1 tar -xzf /tmp/mlf-backup-YYYYMMDD-HHMMSS.tar.gz -C /var/www/html/wp-content/plugins/
```

## 📊 Métriques de qualité

### Seuils acceptables :
- **Taux de réussite des tests :** 100%
- **Temps de réponse admin :** < 3 secondes
- **Temps de réponse frontend :** < 2 secondes
- **Aucune erreur PHP** dans les logs

### Monitoring continu :
- Health check DB quotidien
- Tests de régression après chaque modification
- Sauvegarde automatique avant déploiement

## 🔄 Workflow de développement recommandé

### Avant toute modification :
1. Créer une branche Git
2. Exécuter les tests de base

### Après modification :
1. Tests unitaires rapides
2. Tests de régression complets
3. Vérification manuelle de l'interface
4. Validation sur données réelles

### Avant mise en production :
1. Déploiement sécurisé avec tests
2. Monitoring post-déploiement
3. Validation utilisateur final

---

**🛡️ Avec ce système, chaque modification est testée et validée automatiquement !**
