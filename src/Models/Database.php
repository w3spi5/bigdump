<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;
use mysqli;
use mysqli_result;
use RuntimeException;

/**
 * Database Class - MySQL Connection Manager
 *
 * This class encapsulates the MySQLi connection and provides
 * secure methods for query execution.
 *
 * @package BigDump\Models
 * @author  MVC Refactoring
 * @version 2.5
 */
class Database
{
    /**
     * MySQLi Instance
     * @var mysqli|null
     */
    private ?mysqli $connection = null;

    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Last error
     * @var string
     */
    private string $lastError = '';

    /**
     * Number of executed queries
     * @var int
     */
    private int $queryCount = 0;

    /**
     * Test mode enabled
     * @var bool
     */
    private bool $testMode = false;

    /**
     * Constructor
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->testMode = $config->get('test_mode', false);
    }

    /**
     * Establishes the database connection
     *
     * @return bool True if connection succeeds
     * @throws RuntimeException If connection fails
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

        // Disable MySQLi error reporting to handle errors manually
        mysqli_report(MYSQLI_REPORT_OFF);

        // Parse server to extract host, port, socket
        $server = $this->parseServer($db['server']);

        // Establish connection
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

        // Set charset
        if (!empty($db['charset'])) {
            // Validate charset name to prevent SQL injection
            // MySQL charset names only contain alphanumeric characters
            $safeCharset = preg_replace('/[^a-zA-Z0-9]/', '', $db['charset']);
            if ($safeCharset !== $db['charset']) {
                throw new RuntimeException("Invalid charset name: {$db['charset']}");
            }

            if (!$this->connection->set_charset($safeCharset)) {
                // Fallback with SET NAMES
                $this->connection->query("SET NAMES `{$safeCharset}`");
            }
        }

        // Execute pre-queries
        $this->executePreQueries();

        return true;
    }

    /**
     * Parses server string to extract host, port and socket
     *
     * @param string $server Server string
     * @return array{host: string, port: int, socket: string} Parsed components
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

        // Check if it's a Unix socket
        if (str_contains($server, '/')) {
            // Format: hostname:/path/to/socket or :/path/to/socket
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
     * Executes configured pre-queries
     *
     * @return void
     * @throws RuntimeException If a pre-query fails
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
     * Executes an SQL query
     *
     * @param string $query SQL query
     * @return mysqli_result|bool Query result
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
     * Retrieves current connection charset
     *
     * @return string Charset or empty string
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
     * Checks if connection is established
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        if ($this->testMode) {
            return true;
        }

        return $this->connection !== null && $this->connection->ping();
    }

    /**
     * Retrieves last error
     *
     * @return string Error message
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Retrieves number of executed queries
     *
     * @return int Number of queries
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Escapes a string for SQL usage
     *
     * @param string $string String to escape
     * @return string Escaped string
     */
    public function escape(string $string): string
    {
        if ($this->testMode || $this->connection === null) {
            return addslashes($string);
        }

        return $this->connection->real_escape_string($string);
    }

    /**
     * Retrieves last insert ID
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
     * Retrieves number of affected rows
     *
     * @return int Number of rows
     */
    public function getAffectedRows(): int
    {
        if ($this->testMode || $this->connection === null) {
            return 0;
        }

        return (int) $this->connection->affected_rows;
    }

    /**
     * Closes the connection
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
     * Retrieves database name
     *
     * @return string Database name
     */
    public function getDatabaseName(): string
    {
        return $this->config->get('db_name', '');
    }

    /**
     * Retrieves server name
     *
     * @return string Server name
     */
    public function getServerName(): string
    {
        return $this->config->get('db_server', 'localhost');
    }

    /**
     * Checks if test mode is enabled
     *
     * @return bool True if test mode
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Destructor - closes the connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
