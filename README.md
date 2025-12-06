# BigDump 2.0 - Staggered MySQL Dump Importer

BigDump est un outil PHP permettant d'importer des dumps MySQL volumineux sur des serveurs web avec des limites de temps d'exécution strictes. Cette version 2.0 est une refactorisation complète en architecture MVC orientée objet.

## Fonctionnalités

- **Import échelonné** : Importe les dumps par sessions pour contourner les limites de timeout
- **Support multi-format** : Fichiers `.sql`, `.gz` (gzip), et `.csv`
- **Mode AJAX** : Import sans rafraîchissement de page (recommandé)
- **Interface moderne** : Design responsive et intuitif
- **Sécurité renforcée** : Protection contre path traversal, XSS, et autres vulnérabilités
- **Gestion des erreurs** : Messages d'erreur clairs et détaillés
- **Support UTF-8** : Gestion correcte des caractères multi-octets et BOM

## Prérequis

- PHP 8.1 ou supérieur
- Extension MySQLi
- Serveur MySQL/MariaDB
- Permissions d'écriture sur le dossier `uploads/`

## Installation

1. **Télécharger** le projet sur votre serveur web :
   ```bash
   git clone <repository> bigdump
   # ou télécharger et extraire l'archive
   ```

2. **Configurer** la base de données dans `config/config.php` :
   ```php
   return [
       'db_server' => 'localhost',
       'db_name' => 'votre_base',
       'db_username' => 'votre_utilisateur',
       'db_password' => 'votre_mot_de_passe',
       'db_connection_charset' => 'utf8mb4',
   ];
   ```

3. **Définir les permissions** :
   ```bash
   chmod 755 uploads/
   ```

4. **Accéder** à BigDump via votre navigateur :
   ```
   http://votre-site.com/bigdump/public/index.php
   ```

## Structure du projet

```
bigdump/
├── config/
│   └── config.php          # Configuration utilisateur
├── public/
│   └── index.php           # Point d'entrée
├── src/
│   ├── Config/
│   │   └── Config.php      # Gestionnaire de configuration
│   ├── Controllers/
│   │   └── BigDumpController.php
│   ├── Core/
│   │   ├── Application.php # Application principale
│   │   ├── Request.php     # Encapsulation requête HTTP
│   │   ├── Response.php    # Encapsulation réponse HTTP
│   │   ├── Router.php      # Routeur
│   │   └── View.php        # Moteur de templates
│   ├── Models/
│   │   ├── Database.php    # Connexion MySQL
│   │   ├── FileHandler.php # Gestion des fichiers
│   │   ├── ImportSession.php # État d'une session
│   │   └── SqlParser.php   # Analyseur SQL
│   ├── Services/
│   │   ├── AjaxService.php # Réponses AJAX
│   │   └── ImportService.php # Service d'import
│   └── Views/
│       ├── error.php       # Page d'erreur
│       ├── home.php        # Page d'accueil
│       ├── import.php      # Page d'import
│       └── layout.php      # Template principal
├── uploads/                # Dossier des dumps
├── LICENSE
└── README.md
```

## Configuration avancée

### Options d'import

```php
return [
    // Nombre de lignes par session (réduire si timeout)
    'linespersession' => 3000,

    // Délai entre sessions (ms) pour réduire la charge
    'delaypersession' => 0,

    // Mode AJAX (recommandé)
    'ajax' => true,

    // Mode test (parse sans exécuter)
    'test_mode' => false,
];
```

### Configuration CSV

```php
return [
    'csv_insert_table' => 'ma_table',
    'csv_preempty_table' => false,
    'csv_delimiter' => ',',
    'csv_enclosure' => '"',
];
```

### Requêtes préliminaires

```php
return [
    'pre_queries' => [
        'SET foreign_key_checks = 0',
        'SET unique_checks = 0',
    ],
];
```

## Bugs corrigés par rapport à l'original

1. **Sanitization excessive** : Les caractères UTF-8 valides sont maintenant préservés
2. **Gestion des quotes** : Détection correcte de `\\'` (backslash échappé)
3. **Fichiers gzip** : Correction de la gestion du seek
4. **Path traversal** : Protection contre les attaques `../`
5. **XSS** : Échappement systématique des sorties HTML
6. **Race conditions** : Gestion atomique des uploads
7. **Mémoire** : Limite configurable par requête
8. **BOM** : Support UTF-8, UTF-16 et UTF-32
9. **DELIMITER** : Détection uniquement hors des chaînes
10. **CSV** : Parsing correct des champs avec délimiteurs internes

## Sécurité

- **Ne laissez JAMAIS** BigDump et vos fichiers dump sur un serveur de production après usage
- Les fichiers dump peuvent contenir des données sensibles
- Le répertoire `uploads/` est protégé par `.htaccess`
- Supprimez l'application dès que l'import est terminé

## Licence

GPL-2.0-or-later

## Crédits

- **Original** : Alexey Ozerov (http://www.ozerov.de/bigdump)
- **Refactorisation MVC** : Version 2.0 avec architecture orientée objet
