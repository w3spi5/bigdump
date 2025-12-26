# src/Models/

## 📁 Rôle
Modèles de données : connexion BDD, manipulation de fichiers, état de session d'import, parsing SQL.

## 📄 Fichiers principaux
- **Database.php** - Wrapper MySQLi : connexion, query, pre/post-queries, escaping
- **FileHandler.php** - Lecture de fichiers SQL/GZ/CSV, gestion offset, BOM
- **ImportSession.php** - État d'une session d'import (offset, queries, lignes, stats)
- **SqlParser.php** - Parser SQL stateful (multi-ligne, DELIMITER, strings, comments)

## 🔗 Dépendances
- `Database` utilise `Config` pour les credentials
- `FileHandler` détecte automatiquement gzip
- `SqlParser` utilisé par `ImportService` pour extraire les requêtes
- `ImportSession` sérialisé entre sessions AJAX

## ⚠️ Points d'attention
- **SqlParser** : état persistant entre sessions (inString, delimiter, currentQuery)
- **Database** : `executePreQueries()` et `executePostQueries()` pour optimisation
- **FileHandler** : gère les fichiers > 2GB via offset
- **ImportSession** : calcule les statistiques et estimations

## 🛠️ Modifications fréquentes
- `SqlParser.php` pour bugs de parsing SQL
- `Database.php` pour optimisations MySQL
- `ImportSession.php` pour nouvelles statistiques
