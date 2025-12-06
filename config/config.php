<?php

/**
 * BigDump 2.0 - Configuration
 *
 * Modifiez ce fichier pour configurer votre importation MySQL.
 * Toutes les options sont documentées ci-dessous.
 *
 * @package BigDump
 * @version 2.0.0
 */

return [
    // =========================================================================
    // CONFIGURATION BASE DE DONNÉES (OBLIGATOIRE)
    // =========================================================================

    /**
     * Serveur MySQL
     * Format: 'hostname' ou 'hostname:port' ou 'localhost:/path/to/socket'
     */
    'db_server' => 'localhost',

    /**
     * Nom de la base de données
     */
    'db_name' => '',

    /**
     * Nom d'utilisateur MySQL
     */
    'db_username' => '',

    /**
     * Mot de passe MySQL
     */
    'db_password' => '',

    /**
     * Charset de la connexion
     * Doit correspondre au charset du fichier dump
     * Valeurs courantes: 'utf8mb4', 'utf8', 'latin1', 'cp1251', 'koi8r'
     * Voir: https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html
     */
    'db_connection_charset' => 'utf8mb4',

    // =========================================================================
    // CONFIGURATION DE L'IMPORT (OPTIONNEL)
    // =========================================================================

    /**
     * Nom du fichier dump à importer
     * Laissez vide pour afficher la liste des fichiers disponibles
     */
    'filename' => '',

    /**
     * Mode AJAX
     * true: Import sans rafraîchissement de la page (recommandé)
     * false: Import avec rafraîchissement classique
     */
    'ajax' => true,

    /**
     * Nombre de lignes à traiter par session
     * Réduisez cette valeur si vous avez des erreurs de timeout
     * Augmentez pour des imports plus rapides sur serveurs puissants
     */
    'linespersession' => 3000,

    /**
     * Délai en millisecondes entre chaque session
     * Utilisez pour réduire la charge serveur (0 = pas de délai)
     */
    'delaypersession' => 0,

    // =========================================================================
    // CONFIGURATION CSV (OPTIONNEL - uniquement pour fichiers .csv)
    // =========================================================================

    /**
     * Table de destination pour les fichiers CSV
     * OBLIGATOIRE si vous importez un fichier CSV
     */
    'csv_insert_table' => '',

    /**
     * Vider la table avant l'import CSV
     */
    'csv_preempty_table' => false,

    /**
     * Délimiteur de champs CSV
     */
    'csv_delimiter' => ',',

    /**
     * Caractère d'encadrement des champs CSV
     */
    'csv_enclosure' => '"',

    /**
     * Ajouter des quotes autour des valeurs CSV
     * Mettez false si vos données CSV ont déjà des quotes
     */
    'csv_add_quotes' => true,

    /**
     * Ajouter des slashes d'échappement pour CSV
     * Mettez false si vos données CSV sont déjà échappées
     */
    'csv_add_slashes' => true,

    // =========================================================================
    // CONFIGURATION AVANCÉE (OPTIONNEL)
    // =========================================================================

    /**
     * Marqueurs de commentaires SQL
     * Les lignes commençant par ces chaînes seront ignorées
     */
    'comment_markers' => [
        '#',
        '-- ',
        'DELIMITER',
        '/*!',
        // Décommentez si nécessaire:
        // '---',           // Pour certains dumps propriétaires
        // 'CREATE DATABASE', // Pour ignorer les CREATE DATABASE
    ],

    /**
     * Requêtes SQL à exécuter au début de chaque session
     * Utile pour désactiver les vérifications de clés étrangères
     */
    'pre_queries' => [
        // Décommentez si nécessaire:
        // 'SET foreign_key_checks = 0',
        // 'SET unique_checks = 0',
        // 'SET autocommit = 0',
    ],

    /**
     * Délimiteur de fin de requête par défaut
     * Peut être modifié par DELIMITER dans le dump
     */
    'delimiter' => ';',

    /**
     * Caractère de quote des chaînes SQL
     * Changez en '"' si votre dump utilise des guillemets doubles
     */
    'string_quotes' => "'",

    /**
     * Nombre maximum de lignes par requête SQL
     * Augmentez si vous avez des requêtes très longues (procédures stockées)
     */
    'max_query_lines' => 300,

    /**
     * Répertoire des fichiers uploadés
     * Laissez vide pour utiliser le répertoire 'uploads' par défaut
     */
    'upload_dir' => '',

    /**
     * Mode test
     * true: Lit le fichier sans exécuter les requêtes SQL
     * Utile pour vérifier que le fichier est lisible
     */
    'test_mode' => false,

    /**
     * Mode debug
     * true: Affiche les traces d'erreurs détaillées
     */
    'debug' => false,

    /**
     * Taille du buffer de lecture (en octets)
     * Ne modifiez que si vous avez des problèmes de mémoire
     */
    'data_chunk_length' => 16384,

    /**
     * Extensions de fichiers autorisées
     */
    'allowed_extensions' => ['sql', 'gz', 'csv'],

    /**
     * Taille mémoire maximale pour une requête (en octets)
     * Protection contre les requêtes infinies (10 MB par défaut)
     */
    'max_query_memory' => 10485760,
];
