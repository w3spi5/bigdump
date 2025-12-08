<?php

declare(strict_types=1);

namespace BigDump\Config;

use RuntimeException;

/**
 * Config Class - Configuration Manager
 *
 * This class loads and manages the application configuration
 * from an external PHP file
 *
 * @package BigDump\Config
 * @author  w3spi5
 */
class Config
{
    /**
     * Configuration values
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * Default values
     * @var array<string, mixed>
     */
    private static array $defaults = [
        // Database configuration
        'db_server' => 'localhost',
        'db_name' => '',
        'db_username' => '',
        'db_password' => '',
        'db_connection_charset' => 'utf8mb4',

        // Import configuration
        'filename' => '',
        'ajax' => true,
        'linespersession' => 3000,
        'auto_tuning' => true,        // Enable automatic batch size optimization
        'min_batch_size' => 3000,     // Minimum batch size (safety floor)
        'max_batch_size' => 50000,    // Maximum batch size (ceiling)
        'delaypersession' => 0,

        // CSV configuration
        'csv_insert_table' => '',
        'csv_preempty_table' => false,
        'csv_delimiter' => ',',
        'csv_enclosure' => '"',
        'csv_add_quotes' => true,
        'csv_add_slashes' => true,

        // SQL comment markers
        'comment_markers' => [
            '#',
            '-- ',
            'DELIMITER',
            '/*!',
        ],

        // SQL pre-queries
        'pre_queries' => [],

        // Default query delimiter
        'delimiter' => ';',

        // String quote character
        'string_quotes' => "'",

        // Maximum number of lines per query
        'max_query_lines' => 300,

        // Upload directory
        'upload_dir' => '',

        // Test mode (do not execute queries)
        'test_mode' => false,

        // Debug mode
        'debug' => false,

        // Read chunk size
        'data_chunk_length' => 16384,

        // Allowed file extensions
        'allowed_extensions' => ['sql', 'gz', 'csv'],

        // Maximum memory size for a query (in bytes)
        'max_query_memory' => 10485760, // 10 MB
    ];

    /**
     * Constructor
     *
     * @param string $configFile Path to the configuration file
     * @throws RuntimeException If the configuration file does not exist
     */
    public function __construct(string $configFile)
    {
        // Initialize with default values
        $this->values = self::$defaults;

        // Load user configuration
        if (file_exists($configFile)) {
            $userConfig = $this->loadConfigFile($configFile);
            $this->values = array_merge($this->values, $userConfig);
        }

        // Set default upload directory if not specified
        if (empty($this->values['upload_dir'])) {
            $this->values['upload_dir'] = dirname($configFile, 2) . '/uploads';
        }

        // Validate configuration
        $this->validate();
    }

    /**
     * Loads a PHP configuration file
     *
     * @param string $file File path
     * @return array<string, mixed> Loaded configuration
     */
    private function loadConfigFile(string $file): array
    {
        $config = [];

        // The config file may define variables
        // that will be retrieved in $config
        ob_start();
        $result = include $file;
        ob_end_clean();

        // If the file returns an array, use it
        if (is_array($result)) {
            return $result;
        }

        // Otherwise, extract defined variables
        // (compatibility with old format)
        return $config;
    }

    /**
     * Validates the configuration
     *
     * @return void
     * @throws RuntimeException If the configuration is invalid
     */
    private function validate(): void
    {
        // Check that the charset is valid
        $validCharsets = [
            'utf8', 'utf8mb4', 'latin1', 'latin2', 'ascii',
            'cp1250', 'cp1251', 'cp1252', 'cp1256', 'cp1257',
            'greek', 'hebrew', 'koi8r', 'koi8u', 'sjis',
            'big5', 'gb2312', 'gbk', 'euckr', 'eucjpms',
        ];

        $charset = strtolower($this->values['db_connection_charset']);
        if (!empty($charset) && !in_array($charset, $validCharsets, true)) {
            // Do not block, just warn
            error_log("BigDump Warning: Unknown charset '{$charset}'");
        }

        // Check numeric values
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
     * Retrieves a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * Sets a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Value
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $this->values[$key] = $value;
        return $this;
    }

    /**
     * Checks if a configuration key exists
     *
     * @param string $key Configuration key
     * @return bool True if the key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Retrieves all configuration values
     *
     * @return array<string, mixed> All values
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Retrieves the database configuration
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
     * Retrieves the CSV configuration
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
     * Checks if the database configuration is complete
     *
     * @return bool True if the configuration is complete
     */
    public function isDatabaseConfigured(): bool
    {
        return !empty($this->values['db_name'])
            && !empty($this->values['db_username']);
    }

    /**
     * Retrieves the PHP maximum upload size
     *
     * @return int Size in bytes
     */
    public function getUploadMaxFilesize(): int
    {
        $uploadMax = ini_get('upload_max_filesize') ?: '2M';
        return $this->parseSize($uploadMax);
    }

    /**
     * Retrieves the PHP maximum POST size
     *
     * @return int Size in bytes
     */
    public function getPostMaxSize(): int
    {
        $postMax = ini_get('post_max_size') ?: '8M';
        return $this->parseSize($postMax);
    }

    /**
     * Parses a PHP size (e.g., "10M", "1G")
     *
     * @param string $size Size to parse
     * @return int Size in bytes
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
     * Retrieves the upload directory
     *
     * @return string Directory path
     */
    public function getUploadDir(): string
    {
        return $this->values['upload_dir'];
    }

    /**
     * Checks if a file extension is allowed
     *
     * @param string $extension Extension to check
     * @return bool True if allowed
     */
    public function isExtensionAllowed(string $extension): bool
    {
        $extension = strtolower($extension);
        return in_array($extension, $this->values['allowed_extensions'], true);
    }
}
