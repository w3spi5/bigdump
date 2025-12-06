<?php

declare(strict_types=1);

namespace BigDump\Core;

/**
 * Classe Request - Encapsule les données de la requête HTTP
 *
 * Cette classe fournit une abstraction sécurisée pour accéder aux données
 * de la requête HTTP ($_GET, $_POST, $_FILES, $_SERVER).
 *
 * @package BigDump\Core
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Request
{
    /**
     * Données GET nettoyées
     * @var array<string, mixed>
     */
    private array $get;

    /**
     * Données POST nettoyées
     * @var array<string, mixed>
     */
    private array $post;

    /**
     * Données FILES
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * Données SERVER
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * Action demandée
     * @var string
     */
    private string $action;

    /**
     * Constructeur - Initialise la requête avec les superglobales
     */
    public function __construct()
    {
        $this->get = $this->sanitizeInput($_GET);
        $this->post = $this->sanitizeInput($_POST);
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->action = $this->determineAction();
    }

    /**
     * Nettoie les données d'entrée de manière sécurisée
     *
     * Contrairement à l'original qui supprimait trop de caractères,
     * cette version préserve les caractères UTF-8 valides tout en
     * supprimant les caractères de contrôle dangereux.
     *
     * @param array<string, mixed> $data Données à nettoyer
     * @return array<string, mixed> Données nettoyées
     */
    private function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Nettoyer la clé
            $cleanKey = $this->sanitizeKey($key);

            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeInput($value);
            } else {
                $sanitized[$cleanKey] = $this->sanitizeValue((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Nettoie une clé de paramètre
     *
     * @param string $key Clé à nettoyer
     * @return string Clé nettoyée
     */
    private function sanitizeKey(string $key): string
    {
        // Les clés ne doivent contenir que des caractères alphanumériques et underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $key) ?? '';
    }

    /**
     * Nettoie une valeur de paramètre
     *
     * Préserve les caractères UTF-8 valides, supprime uniquement
     * les caractères de contrôle (sauf tab, newline, carriage return).
     *
     * @param string $value Valeur à nettoyer
     * @return string Valeur nettoyée
     */
    private function sanitizeValue(string $value): string
    {
        // Supprimer les caractères de contrôle sauf \t, \n, \r
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        // Supprimer les séquences null byte (injection)
        $value = str_replace("\0", '', $value);

        return $value;
    }

    /**
     * Détermine l'action à exécuter basée sur les paramètres de la requête
     *
     * @return string Nom de l'action
     */
    private function determineAction(): string
    {
        if ($this->has('uploadbutton')) {
            return 'upload';
        }

        if ($this->has('delete')) {
            return 'delete';
        }

        if ($this->has('start') && $this->has('fn')) {
            if ($this->has('ajaxrequest')) {
                return 'ajax_import';
            }
            return 'import';
        }

        if ($this->has('fn')) {
            return 'start_import';
        }

        return 'home';
    }

    /**
     * Récupère une valeur GET
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du paramètre
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Récupère une valeur POST
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du paramètre
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Récupère une valeur GET ou POST (GET prioritaire)
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du paramètre
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $this->post[$key] ?? $default;
    }

    /**
     * Vérifie si un paramètre existe (GET ou POST)
     *
     * @param string $key Clé du paramètre
     * @return bool True si le paramètre existe
     */
    public function has(string $key): bool
    {
        return isset($this->get[$key]) || isset($this->post[$key]);
    }

    /**
     * Récupère un entier depuis les paramètres
     *
     * @param string $key Clé du paramètre
     * @param int $default Valeur par défaut
     * @return int Valeur entière
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return (int) floor((float) $value);
    }

    /**
     * Récupère les informations d'un fichier uploadé
     *
     * @param string $key Clé du fichier
     * @return array<string, mixed>|null Informations du fichier ou null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Vérifie si un fichier a été uploadé correctement
     *
     * @param string $key Clé du fichier
     * @return bool True si le fichier est valide
     */
    public function hasValidFile(string $key): bool
    {
        $file = $this->file($key);

        if ($file === null) {
            return false;
        }

        return isset($file['tmp_name'])
            && is_uploaded_file($file['tmp_name'])
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * Récupère l'action demandée
     *
     * @return string Nom de l'action
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Récupère une valeur SERVER
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du paramètre
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Récupère le nom du script PHP
     *
     * @return string Nom du script
     */
    public function getScriptName(): string
    {
        return basename($this->server('SCRIPT_FILENAME', 'index.php'));
    }

    /**
     * Récupère l'URI du script
     *
     * @return string URI du script
     */
    public function getScriptUri(): string
    {
        return $this->server('PHP_SELF', '/index.php');
    }

    /**
     * Vérifie si la requête est de type AJAX
     *
     * @return bool True si requête AJAX
     */
    public function isAjax(): bool
    {
        return $this->has('ajaxrequest')
            || strtolower($this->server('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Récupère toutes les données GET
     *
     * @return array<string, mixed> Données GET
     */
    public function getAllGet(): array
    {
        return $this->get;
    }

    /**
     * Récupère toutes les données POST
     *
     * @return array<string, mixed> Données POST
     */
    public function getAllPost(): array
    {
        return $this->post;
    }
}
