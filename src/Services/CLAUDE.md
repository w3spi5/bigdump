# src/Services/

## 📁 Rôle
Logique métier de l'application : import SQL, AJAX, auto-tuning, batching INSERT.

## 📄 Fichiers principaux
- **ImportService.php** - Cœur de l'import : lecture fichier, parsing, exécution SQL, gestion sessions
- **AjaxService.php** - Génération des réponses XML/JSON pour le mode AJAX
- **AutoTunerService.php** - Ajustement dynamique du batch size selon la RAM disponible
- **InsertBatcherService.php** - Regroupement des INSERT simples en multi-values (x10-50 speedup)

## 🔗 Dépendances
- `ImportService` orchestre tous les autres services
- `InsertBatcherService` appelé par `ImportService` pour chaque INSERT détecté
- `AutoTunerService` calcule `linespersession` au démarrage
- `AjaxService` formate les stats de `ImportSession`

## ⚠️ Points d'attention
- **ImportService** : méthode `executeSession()` = cœur du traitement par batch
- **InsertBatcherService** : limite 16MB par batch (max_allowed_packet MySQL)
- **AutoTunerService** : profils RAM agressifs pour NVMe (10K-200K lignes)
- **AjaxService** : format XML legacy pour compatibilité

## 🛠️ Modifications fréquentes
- `ImportService.php` pour le flux d'import
- `InsertBatcherService.php` pour le batching
- `AutoTunerService.php` pour les profils performance
