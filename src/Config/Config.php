<?php

declare(strict_types=1);

namespace BigDump\Config;

use RuntimeException;

/**
 * Classe Config - Gestionnaire de configuration
 *
 * Cette classe charge et gère la configuration de l'application
 * depuis un fichier PHP externe.
 *
 * @package BigDump\Config
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Config
{
    /**
     * Valeurs de configuration
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * Valeurs par défaut
     * @var array<string, mixed>
     */
    private static array $defaults = [
        // Configuration base de données
        'db_server' => 'localhost',
        'db_name' => '',
        'db_username' => '',
        'db_password' => '',
        'db_connection_charset' => 'utf8mb4',

        // Configuration de l'import
        'filename' => '',
        'ajax' => true,
        'linespersession' => 3000,
        'delaypersession' => 0,

        // Configuration CSV
        'csv_insert_table' => '',
        'csv_preempty_table' => false,
        'csv_delimiter' => ',',
        'csv_enclosure' => '"',
        'csv_add_quotes' => true,
        'csv_add_slashes' => true,

        // Marqueurs de commentaires SQL
        'comment_markers' => [
            '#',
            '-- ',
            'DELIMITER',
            '/*!',
        ],

        // Pré-requêtes SQL
        'pre_queries' => [],

        // Délimiteur de requête par défaut
        'delimiter' => ';',

        // Caractère de quote des chaînes
        'string_quotes' => "'",

        // Nombre maximum de lignes par requête
        'max_query_lines' => 300,

        // Répertoire d'upload
        'upload_dir' => '',

        // Mode test (ne pas exécuter les requêtes)
        'test_mode' => false,

        // Mode debug
        'debug' => false,

        // Taille du chunk de lecture
        'data_chunk_length' => 16384,

        // Extensions de fichiers autorisées
        'allowed_extensions' => ['sql', 'gz', 'csv'],

        // Taille maximale de mémoire pour une requête (en octets)
        'max_query_memory' => 10485760, // 10 MB
    ];

    /**
     * Constructeur
     *
     * @param string $configFile Chemin vers le fichier de configuration
     * @throws RuntimeException Si le fichier de configuration n'existe pas
     */
    public function __construct(string $configFile)
    {
        // Initialiser avec les valeurs par défaut
        $this->values = self::$defaults;

        // Charger la configuration utilisateur
        if (file_exists($configFile)) {
            $userConfig = $this->loadConfigFile($configFile);
            $this->values = array_merge($this->values, $userConfig);
        }

        // Définir le répertoire d'upload par défaut si non spécifié
        if (empty($this->values['upload_dir'])) {
            $this->values['upload_dir'] = dirname($configFile, 2) . '/uploads';
        }

        // Valider la configuration
        $this->validate();
    }

    /**
     * Charge un fichier de configuration PHP
     *
     * @param string $file Chemin du fichier
     * @return array<string, mixed> Configuration chargée
     */
    private function loadConfigFile(string $file): array
    {
        $config = [];

        // Le fichier de config peut définir des variables
        // qui seront récupérées dans $config
        ob_start();
        $result = include $file;
        ob_end_clean();

        // Si le fichier retourne un tableau, l'utiliser
        if (is_array($result)) {
            return $result;
        }

        // Sinon, extraire les variables définies
        // (compatibilité avec l'ancien format)
        return $config;
    }

    /**
     * Valide la configuration
     *
     * @return void
     * @throws RuntimeException Si la configuration est invalide
     */
    private function validate(): void
    {
        // Vérifier que le charset est valide
        $validCharsets = [
            'utf8', 'utf8mb4', 'latin1', 'latin2', 'ascii',
            'cp1250', 'cp1251', 'cp1252', 'cp1256', 'cp1257',
            'greek', 'hebrew', 'koi8r', 'koi8u', 'sjis',
            'big5', 'gb2312', 'gbk', 'euckr', 'eucjpms',
        ];

        $charset = strtolower($this->values['db_connection_charset']);
        if (!empty($charset) && !in_array($charset, $validCharsets, true)) {
            // Ne pas bloquer, juste avertir
            error_log("BigDump Warning: Unknown charset '{$charset}'");
        }

        // Vérifier les valeurs numériques
        if ($this->values['linespersession'] < 1) {
            $this->values['linespersession'] = 3000;
        }

        if ($this->values['max_query_lines'] < 1) {
            $this->values['max_query_lines'] = 300;
        }

        if ($this->values['data_chunk_length'] < 1024) {
            $this->values['data_chunk_length'] = 16384;
        }
    }

    /**
     * Récupère une valeur de configuration
     *
     * @param string $key Clé de configuration
     * @param mixed $default Valeur par défaut si non trouvée
     * @return mixed Valeur de configuration
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * Définit une valeur de configuration
     *
     * @param string $key Clé de configuration
     * @param mixed $value Valeur
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $this->values[$key] = $value;
        return $this;
    }

    /**
     * Vérifie si une clé de configuration existe
     *
     * @param string $key Clé de configuration
     * @return bool True si la clé existe
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Récupère toutes les valeurs de configuration
     *
     * @return array<string, mixed> Toutes les valeurs
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Récupère la configuration de base de données
     *
     * @return array{server: string, name: string, username: string, password: string, charset: string}
     */
    public function getDatabase(): array
    {
        return [
            'server' => $this->values['db_server'],
            'name' => $this->values['db_name'],
            'username' => $this->values['db_username'],
            'password' => $this->values['db_password'],
            'charset' => $this->values['db_connection_charset'],
        ];
    }

    /**
     * Récupère la configuration CSV
     *
     * @return array{table: string, preempty: bool, delimiter: string, enclosure: string, add_quotes: bool, add_slashes: bool}
     */
    public function getCsv(): array
    {
        return [
            'table' => $this->values['csv_insert_table'],
            'preempty' => $this->values['csv_preempty_table'],
            'delimiter' => $this->values['csv_delimiter'],
            'enclosure' => $this->values['csv_enclosure'],
            'add_quotes' => $this->values['csv_add_quotes'],
            'add_slashes' => $this->values['csv_add_slashes'],
        ];
    }

    /**
     * Vérifie si la configuration de base de données est complète
     *
     * @return bool True si la configuration est complète
     */
    public function isDatabaseConfigured(): bool
    {
        return !empty($this->values['db_name'])
            && !empty($this->values['db_username']);
    }

    /**
     * Récupère la taille maximale d'upload PHP
     *
     * @return int Taille en octets
     */
    public function getUploadMaxFilesize(): int
    {
        $uploadMax = ini_get('upload_max_filesize') ?: '2M';
        return $this->parseSize($uploadMax);
    }

    /**
     * Récupère la taille maximale de POST PHP
     *
     * @return int Taille en octets
     */
    public function getPostMaxSize(): int
    {
        $postMax = ini_get('post_max_size') ?: '8M';
        return $this->parseSize($postMax);
    }

    /**
     * Parse une taille PHP (ex: "10M", "1G")
     *
     * @param string $size Taille à parser
     * @return int Taille en octets
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'G':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'M':
                $value *= 1024 * 1024;
                break;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Récupère le répertoire d'upload
     *
     * @return string Chemin du répertoire
     */
    public function getUploadDir(): string
    {
        return $this->values['upload_dir'];
    }

    /**
     * Vérifie si une extension de fichier est autorisée
     *
     * @param string $extension Extension à vérifier
     * @return bool True si autorisée
     */
    public function isExtensionAllowed(string $extension): bool
    {
        $extension = strtolower($extension);
        return in_array($extension, $this->values['allowed_extensions'], true);
    }
}
