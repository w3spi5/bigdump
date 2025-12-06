<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;
use mysqli;
use mysqli_result;
use RuntimeException;

/**
 * Classe Database - Gestionnaire de connexion MySQL
 *
 * Cette classe encapsule la connexion MySQLi et fournit
 * des méthodes sécurisées pour l'exécution des requêtes.
 *
 * @package BigDump\Models
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Database
{
    /**
     * Instance MySQLi
     * @var mysqli|null
     */
    private ?mysqli $connection = null;

    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Dernière erreur
     * @var string
     */
    private string $lastError = '';

    /**
     * Nombre de requêtes exécutées
     * @var int
     */
    private int $queryCount = 0;

    /**
     * Mode test activé
     * @var bool
     */
    private bool $testMode = false;

    /**
     * Constructeur
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->testMode = $config->get('test_mode', false);
    }

    /**
     * Établit la connexion à la base de données
     *
     * @return bool True si la connexion réussit
     * @throws RuntimeException Si la connexion échoue
     */
    public function connect(): bool
    {
        if ($this->testMode) {
            return true;
        }

        if ($this->connection !== null) {
            return true;
        }

        $db = $this->config->getDatabase();

        // Désactiver les rapports d'erreur MySQLi pour gérer les erreurs manuellement
        mysqli_report(MYSQLI_REPORT_OFF);

        // Parser le serveur pour extraire host, port, socket
        $server = $this->parseServer($db['server']);

        // Établir la connexion
        $this->connection = @new mysqli(
            $server['host'],
            $db['username'],
            $db['password'],
            $db['name'],
            $server['port'],
            $server['socket']
        );

        if ($this->connection->connect_error) {
            $this->lastError = $this->connection->connect_error;
            $this->connection = null;
            throw new RuntimeException("Database connection failed: {$this->lastError}");
        }

        // Définir le charset
        if (!empty($db['charset'])) {
            // Validate charset name to prevent SQL injection
            // MySQL charset names only contain alphanumeric characters
            $safeCharset = preg_replace('/[^a-zA-Z0-9]/', '', $db['charset']);
            if ($safeCharset !== $db['charset']) {
                throw new RuntimeException("Invalid charset name: {$db['charset']}");
            }

            if (!$this->connection->set_charset($safeCharset)) {
                // Fallback avec SET NAMES
                $this->connection->query("SET NAMES `{$safeCharset}`");
            }
        }

        // Exécuter les pré-requêtes
        $this->executePreQueries();

        return true;
    }

    /**
     * Parse le serveur pour extraire host, port et socket
     *
     * @param string $server Chaîne serveur
     * @return array{host: string, port: int, socket: string} Composants parsés
     */
    private function parseServer(string $server): array
    {
        $result = [
            'host' => 'localhost',
            'port' => 3306,
            'socket' => '',
        ];

        if (empty($server)) {
            return $result;
        }

        // Vérifier si c'est un socket Unix
        if (str_contains($server, '/')) {
            // Format: hostname:/path/to/socket ou :/path/to/socket
            $parts = explode(':', $server, 2);
            if (count($parts) === 2) {
                $result['host'] = $parts[0] ?: 'localhost';
                $result['socket'] = $parts[1];
            } else {
                $result['socket'] = $server;
            }
        } elseif (str_contains($server, ':')) {
            // Format: hostname:port
            $parts = explode(':', $server);
            $result['host'] = $parts[0];
            $result['port'] = (int) ($parts[1] ?? 3306);
        } else {
            $result['host'] = $server;
        }

        return $result;
    }

    /**
     * Exécute les pré-requêtes configurées
     *
     * @return void
     * @throws RuntimeException Si une pré-requête échoue
     */
    private function executePreQueries(): void
    {
        $preQueries = $this->config->get('pre_queries', []);

        if (!is_array($preQueries) || empty($preQueries)) {
            return;
        }

        foreach ($preQueries as $query) {
            if (!$this->query($query)) {
                throw new RuntimeException("Pre-query failed: {$query}\nError: {$this->lastError}");
            }
        }
    }

    /**
     * Exécute une requête SQL
     *
     * @param string $query Requête SQL
     * @return mysqli_result|bool Résultat de la requête
     */
    public function query(string $query): mysqli_result|bool
    {
        if ($this->testMode) {
            $this->queryCount++;
            return true;
        }

        if ($this->connection === null) {
            $this->lastError = 'Not connected to database';
            return false;
        }

        $result = $this->connection->query($query);

        if ($result === false) {
            $this->lastError = $this->connection->error;
            return false;
        }

        $this->queryCount++;
        $this->lastError = '';

        return $result;
    }

    /**
     * Récupère le charset de connexion actuel
     *
     * @return string Charset ou chaîne vide
     */
    public function getConnectionCharset(): string
    {
        if ($this->testMode || $this->connection === null) {
            return $this->config->get('db_connection_charset', 'utf8mb4');
        }

        $result = $this->connection->query("SHOW VARIABLES LIKE 'character_set_connection'");

        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $result->free();

            if ($row && isset($row['Value'])) {
                return $row['Value'];
            }
        }

        return '';
    }

    /**
     * Vérifie si la connexion est établie
     *
     * @return bool True si connecté
     */
    public function isConnected(): bool
    {
        if ($this->testMode) {
            return true;
        }

        return $this->connection !== null && $this->connection->ping();
    }

    /**
     * Récupère la dernière erreur
     *
     * @return string Message d'erreur
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Récupère le nombre de requêtes exécutées
     *
     * @return int Nombre de requêtes
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Échappe une chaîne pour utilisation SQL
     *
     * @param string $string Chaîne à échapper
     * @return string Chaîne échappée
     */
    public function escape(string $string): string
    {
        if ($this->testMode || $this->connection === null) {
            return addslashes($string);
        }

        return $this->connection->real_escape_string($string);
    }

    /**
     * Récupère l'ID de la dernière insertion
     *
     * @return int ID
     */
    public function getLastInsertId(): int
    {
        if ($this->testMode || $this->connection === null) {
            return 0;
        }

        return (int) $this->connection->insert_id;
    }

    /**
     * Récupère le nombre de lignes affectées
     *
     * @return int Nombre de lignes
     */
    public function getAffectedRows(): int
    {
        if ($this->testMode || $this->connection === null) {
            return 0;
        }

        return (int) $this->connection->affected_rows;
    }

    /**
     * Ferme la connexion
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Récupère le nom de la base de données
     *
     * @return string Nom de la base
     */
    public function getDatabaseName(): string
    {
        return $this->config->get('db_name', '');
    }

    /**
     * Récupère le nom du serveur
     *
     * @return string Nom du serveur
     */
    public function getServerName(): string
    {
        return $this->config->get('db_server', 'localhost');
    }

    /**
     * Vérifie si le mode test est activé
     *
     * @return bool True si mode test
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Destructeur - ferme la connexion
     */
    public function __destruct()
    {
        $this->close();
    }
}
