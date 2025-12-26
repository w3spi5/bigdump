# Services Module

## Overview

## 📄 Fichiers principaux
- **ImportService.php** - Cœur de l'import : lecture fichier, parsing, exécution SQL, gestion sessions, COMMIT frequency (v2.19)
- **AjaxService.php** - Génération des réponses XML/JSON pour le mode AJAX
- **AutoTunerService.php** - Ajustement dynamique du batch size selon la RAM et le profil performance (v2.19)
- **InsertBatcherService.php** - Regroupement des INSERT/INSERT IGNORE en multi-values (x10-50 speedup), taille configurable (v2.19)

## 🔗 Dépendances
- `ImportService` orchestre tous les autres services
- `InsertBatcherService` appelé par `ImportService` pour chaque INSERT détecté
- `AutoTunerService` calcule `linespersession` au démarrage, profile-aware (v2.19)
- `AjaxService` formate les stats de `ImportSession`

## ⚠️ Points d'attention
- **ImportService** : méthode `executeSession()` = cœur du traitement par batch, COMMIT frequency configurable
- **InsertBatcherService** : limite configurable 16MB/32MB par batch selon profil (max_allowed_packet MySQL)
- **AutoTunerService** : profils RAM avec multipliers (1.0x conservative, 1.3x aggressive), safety margins (80%/70%)
- **AjaxService** : format XML legacy pour compatibilité

## 🆕 Fonctionnalités v2.19

### AutoTunerService
- Memory caching (1s TTL) pour réduire les appels `memory_get_usage()`
- System resources cache (60s TTL)
- Profile-aware: multiplier 1.3x, safety margin 70%, max batch 2M en mode aggressive
- `getMetrics()` expose le profil effectif

### InsertBatcherService
- Taille de batch configurable via constructeur (`$batchSize`, `$maxBatchBytes`)
- Support INSERT IGNORE batching
- Adaptive batch sizing basé sur la taille moyenne des rows
- Métriques d'efficacité: `rows_batched`, `reduction_ratio`, `batch_efficiency`

### ImportService
- COMMIT frequency configurable (1 conservative, 3 aggressive)
- Passe les valeurs de config à InsertBatcherService

## 🛠️ Modifications fréquentes
- `ImportService.php` pour le flux d'import
- `InsertBatcherService.php` pour le batching
- `AutoTunerService.php` pour les profils performance
