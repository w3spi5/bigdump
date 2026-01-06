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
     * Temporary runtime overrides
     * @var array<string, mixed>
     */
    private array $temporaryOverrides = [];

    /**
     * Effective performance profile after validation
     * @var string
     */
    private string $effectiveProfile = 'conservative';

    /**
     * Whether profile was downgraded due to memory constraints
     * @var bool
     */
    private bool $profileDowngraded = false;

    /**
     * Cached BZ2 extension availability check result
     * @var bool|null
     */
    private static ?bool $bz2Supported = null;

    /**
     * Profile-specific default values
     * @var array<string, array<string, mixed>>
     */
    private static array $profileDefaults = [
        'conservative' => [
            'file_buffer_size' => 65536,      // 64KB
            'insert_batch_size' => 2000,
            'max_batch_bytes' => 16777216,    // 16MB
            'commit_frequency' => 1,
            'linespersession' => 5000,        // v2.25: increased from 3000
        ],
        'aggressive' => [
            'file_buffer_size' => 131072,     // 128KB
            'insert_batch_size' => 5000,
            'max_batch_bytes' => 33554432,    // 32MB
            'commit_frequency' => 3,
            'linespersession' => 10000,       // v2.25: increased from 5000
        ],
    ];

    /**
     * Minimum PHP memory_limit for aggressive mode (128MB)
     */
    private const AGGRESSIVE_MIN_MEMORY = 134217728;

    /**
     * Buffer size constraints
     */
    private const MIN_BUFFER_SIZE = 65536;   // 64KB
    private const MAX_BUFFER_SIZE = 262144;  // 256KB

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

        // Persistent database connections (v2.25+)
        // Reduces connection overhead for large imports with many sessions.
        // WARNING: Use with caution on shared hosting - may exhaust connection pool.
        // Recommended only for VPS/dedicated servers with controlled connection limits.
        'persistent_connections' => false,

        // Import configuration
        'filename' => '',
        'ajax' => true,
        'linespersession' => 5000,        // v2.25: increased from 3000
        'auto_tuning' => true,            // Enable automatic batch size optimization
        'min_batch_size' => 5000,         // v2.25: increased from 3000
        'max_batch_size' => 50000,        // Maximum batch size (ceiling)
        'delaypersession' => 0,

        // Performance profile (v2.19+)
        'performance_profile' => 'conservative', // 'conservative' or 'aggressive'

        // Auto-aggressive mode for large files (v2.25+)
        // Files larger than this threshold automatically use aggressive profile
        'auto_profile_threshold' => 104857600, // 100MB

        // Profile-dependent options (defaults are conservative values)
        // These are overridden based on effective profile during validation
        'file_buffer_size' => 65536,      // 64KB conservative, 128KB aggressive
        'insert_batch_size' => 2000,      // 2000 conservative, 5000 aggressive
        'max_batch_bytes' => 16777216,    // 16MB conservative, 32MB aggressive
        'commit_frequency' => 1,          // 1 conservative, 3 aggressive

        // CSV configuration
        'csv_insert_table' => '',
        'csv_preempty_table' => false,
        'csv_delimiter' => ',',
        'csv_enclosure' => '"',
        'csv_add_quotes' => true,
        'csv_add_slashes' => true,

        // SQL comment markers
        // Note: /*! (MySQL conditional comments) are NOT treated as comments
        // because they contain valid SQL code that MySQL executes
        // DELIMITER is also not a comment - it's a client command
        'comment_markers' => [
            '#',
            '-- ',
        ],

        // SQL pre-queries - Performance optimizations enabled by default
        'pre_queries' => [
            'SET autocommit=0',
            'SET unique_checks=0',
            'SET foreign_key_checks=0',
            'SET sql_log_bin=0',
        ],

        // SQL post-queries - Restore settings after import
        'post_queries' => [
            'SET unique_checks=1',
            'SET foreign_key_checks=1',
            'SET autocommit=1',
        ],

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

        // Allowed file extensions (v2.20+: includes bz2)
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],

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

        // Validate configuration (includes profile validation)
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
        // The config file may define variables or return an array
        ob_start();
        $result = include $file;
        ob_end_clean();

        // If the file returns an array, use it directly
        if (is_array($result)) {
            return $result;
        }

        // Otherwise, extract defined variables (legacy format compatibility)
        // Old BigDump format used variables like $db_server, $db_name, etc.
        $config = [];
        $legacyVarNames = [
            'db_server', 'db_name', 'db_username', 'db_password',
            'db_connection_charset', 'filename', 'ajax', 'linespersession',
            'auto_tuning', 'min_batch_size', 'max_batch_size', 'delaypersession',
            'csv_insert_table', 'csv_preempty_table', 'csv_delimiter',
            'csv_enclosure', 'csv_add_quotes', 'csv_add_slashes',
            'comment_markers', 'pre_queries', 'delimiter', 'string_quotes',
            'max_query_lines', 'upload_dir', 'test_mode', 'debug',
            'data_chunk_length', 'allowed_extensions', 'max_query_memory',
            // Performance profile options (v2.19+)
            'performance_profile', 'file_buffer_size', 'insert_batch_size',
            'max_batch_bytes', 'commit_frequency',
            // Auto-aggressive mode (v2.25+)
            'auto_profile_threshold',
            // Persistent connections (v2.25+)
            'persistent_connections',
        ];

        // Get defined variables after including the file
        $definedVars = get_defined_vars();

        foreach ($legacyVarNames as $varName) {
            if (isset($definedVars[$varName])) {
                $config[$varName] = $definedVars[$varName];
            }
        }

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
        // Validate and apply performance profile first
        $this->validatePerformanceProfile();

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
            $this->values['linespersession'] = 5000;
        }

        if ($this->values['max_query_lines'] < 1) {
            $this->values['max_query_lines'] = 300;
        }

        if ($this->values['data_chunk_length'] < 1024) {
            $this->values['data_chunk_length'] = 16384;
        }

        // Validate buffer size within allowed range
        $this->validateBufferSize();
    }

    /**
     * Validates and applies performance profile settings
     *
     * @return void
     */
    private function validatePerformanceProfile(): void
    {
        $requestedProfile = $this->values['performance_profile'] ?? 'conservative';

        // Validate profile value
        if (!in_array($requestedProfile, ['conservative', 'aggressive'], true)) {
            error_log("BigDump Warning: Invalid performance_profile '{$requestedProfile}', using 'conservative'");
            $requestedProfile = 'conservative';
        }

        // If aggressive mode requested, check memory requirements
        if ($requestedProfile === 'aggressive') {
            $memoryLimit = $this->getPhpMemoryLimitBytes();

            if ($memoryLimit !== -1 && $memoryLimit < self::AGGRESSIVE_MIN_MEMORY) {
                $memoryLimitMB = round($memoryLimit / 1024 / 1024);
                $requiredMB = round(self::AGGRESSIVE_MIN_MEMORY / 1024 / 1024);
                error_log(
                    "BigDump Warning: Aggressive mode requires {$requiredMB}MB+ memory_limit, " .
                    "current limit is {$memoryLimitMB}MB. Falling back to conservative mode."
                );
                $this->effectiveProfile = 'conservative';
                $this->profileDowngraded = true;
            } else {
                $this->effectiveProfile = 'aggressive';
            }
        } else {
            $this->effectiveProfile = 'conservative';
        }

        // Apply profile defaults for options not explicitly set by user
        $this->applyProfileDefaults();
    }

    /**
     * Applies profile-specific defaults for options not explicitly configured
     *
     * @return void
     */
    private function applyProfileDefaults(): void
    {
        $profileDefaults = self::$profileDefaults[$this->effectiveProfile];

        // For each profile-dependent option, use profile default if user didn't specify
        // We check against the base defaults to see if user overrode
        foreach ($profileDefaults as $key => $profileValue) {
            // If user config matches the base default, apply profile-specific value
            // This allows profile to cascade while respecting explicit user overrides
            if ($this->values[$key] === self::$defaults[$key]) {
                $this->values[$key] = $profileValue;
            }
        }
    }

    /**
     * Validates the file buffer size within allowed range
     *
     * @return void
     */
    private function validateBufferSize(): void
    {
        $bufferSize = $this->values['file_buffer_size'];

        if ($bufferSize < self::MIN_BUFFER_SIZE) {
            error_log(
                "BigDump Warning: file_buffer_size {$bufferSize} below minimum " .
                self::MIN_BUFFER_SIZE . ", using minimum."
            );
            $this->values['file_buffer_size'] = self::MIN_BUFFER_SIZE;
        } elseif ($bufferSize > self::MAX_BUFFER_SIZE) {
            error_log(
                "BigDump Warning: file_buffer_size {$bufferSize} exceeds maximum " .
                self::MAX_BUFFER_SIZE . ", using maximum."
            );
            $this->values['file_buffer_size'] = self::MAX_BUFFER_SIZE;
        }
    }

    /**
     * Gets the PHP memory_limit in bytes
     *
     * @return int Memory limit in bytes, or -1 if unlimited
     */
    private function getPhpMemoryLimitBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === false || $memoryLimit === '' || $memoryLimit === '-1') {
            return -1; // Unlimited
        }

        return $this->parseSize($memoryLimit);
    }

    /**
     * Returns the effective performance profile after validation
     *
     * This method returns the actual profile being used, which may differ
     * from the configured profile if memory constraints forced a downgrade.
     *
     * @return string 'conservative' or 'aggressive'
     */
    public function getEffectiveProfile(): string
    {
        return $this->effectiveProfile;
    }

    /**
     * Returns whether the profile was downgraded due to memory constraints
     *
     * @return bool True if profile was downgraded from aggressive to conservative
     */
    public function wasProfileDowngraded(): bool
    {
        return $this->profileDowngraded;
    }

    /**
     * Returns profile-specific information for debugging
     *
     * @return array{
     *     requested_profile: string,
     *     effective_profile: string,
     *     was_downgraded: bool,
     *     memory_limit_bytes: int,
     *     aggressive_min_memory: int,
     *     profile_settings: array<string, mixed>
     * }
     */
    public function getProfileInfo(): array
    {
        return [
            'requested_profile' => $this->values['performance_profile'],
            'effective_profile' => $this->effectiveProfile,
            'was_downgraded' => $this->profileDowngraded,
            'memory_limit_bytes' => $this->getPhpMemoryLimitBytes(),
            'aggressive_min_memory' => self::AGGRESSIVE_MIN_MEMORY,
            'profile_settings' => [
                'file_buffer_size' => $this->values['file_buffer_size'],
                'insert_batch_size' => $this->values['insert_batch_size'],
                'max_batch_bytes' => $this->values['max_batch_bytes'],
                'commit_frequency' => $this->values['commit_frequency'],
            ],
        ];
    }

    /**
     * Retrieves a configuration value
     *
     * Checks temporary overrides first, then falls back to stored values.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check temporary overrides first
        if (array_key_exists($key, $this->temporaryOverrides)) {
            return $this->temporaryOverrides[$key];
        }

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
     * Sets a temporary configuration override
     *
     * Temporary overrides take precedence over regular values but are not persisted.
     * Useful for runtime adjustments like auto-aggressive mode for large files.
     *
     * When setting 'performance_profile' temporarily, this also re-applies
     * profile defaults to ensure all profile-dependent settings are updated.
     *
     * @param string $key Configuration key
     * @param mixed $value Value
     * @return self
     */
    public function setTemporary(string $key, mixed $value): self
    {
        $this->temporaryOverrides[$key] = $value;

        // If changing performance_profile, update effective profile and re-apply defaults
        if ($key === 'performance_profile') {
            $this->reapplyProfileForTemporaryOverride($value);
        }

        return $this;
    }

    /**
     * Re-applies profile settings when temporary override changes performance_profile
     *
     * @param string $profile The new profile value
     * @return void
     */
    private function reapplyProfileForTemporaryOverride(string $profile): void
    {
        // Validate the profile
        if (!in_array($profile, ['conservative', 'aggressive'], true)) {
            return;
        }

        // Check memory requirements for aggressive mode
        if ($profile === 'aggressive') {
            $memoryLimit = $this->getPhpMemoryLimitBytes();
            if ($memoryLimit !== -1 && $memoryLimit < self::AGGRESSIVE_MIN_MEMORY) {
                // Cannot use aggressive, keep conservative
                error_log(
                    "BigDump: Auto-aggressive mode requested but memory_limit is insufficient. " .
                    "Keeping conservative mode."
                );
                unset($this->temporaryOverrides['performance_profile']);
                return;
            }
        }

        // Update effective profile
        $this->effectiveProfile = $profile;

        // Apply profile defaults as temporary overrides for profile-dependent options
        // Only override if user hasn't explicitly set them
        $profileDefaults = self::$profileDefaults[$profile];
        foreach ($profileDefaults as $key => $profileValue) {
            // Skip if user has explicitly set this value (different from base default)
            if ($this->values[$key] !== self::$defaults[$key]) {
                continue;
            }
            // Skip if already overridden temporarily
            if (array_key_exists($key, $this->temporaryOverrides) && $key !== 'performance_profile') {
                continue;
            }
            // Apply profile default as temporary override
            $this->temporaryOverrides[$key] = $profileValue;
        }
    }

    /**
     * Clears a temporary configuration override
     *
     * @param string $key Configuration key to clear
     * @return self
     */
    public function clearTemporary(string $key): self
    {
        unset($this->temporaryOverrides[$key]);
        return $this;
    }

    /**
     * Clears all temporary configuration overrides
     *
     * @return self
     */
    public function clearAllTemporary(): self
    {
        $this->temporaryOverrides = [];
        return $this;
    }

    /**
     * Checks if a temporary override exists for a key
     *
     * @param string $key Configuration key
     * @return bool True if temporary override exists
     */
    public function hasTemporary(string $key): bool
    {
        return array_key_exists($key, $this->temporaryOverrides);
    }

    /**
     * Checks if a configuration key exists
     *
     * @param string $key Configuration key
     * @return bool True if the key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->temporaryOverrides)
            || array_key_exists($key, $this->values);
    }

    /**
     * Retrieves all configuration values
     *
     * @return array<string, mixed> All values
     */
    public function all(): array
    {
        return array_merge($this->values, $this->temporaryOverrides);
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

    /**
     * Checks if BZ2 compression is supported by PHP
     *
     * This method caches the result of function_exists('bzopen')
     * for repeated checks during a request.
     *
     * @return bool True if PHP bz2 extension is available
     */
    public static function isBz2Supported(): bool
    {
        if (self::$bz2Supported === null) {
            self::$bz2Supported = function_exists('bzopen');
        }

        return self::$bz2Supported;
    }

    /**
     * Resets the cached BZ2 support check (for testing purposes)
     *
     * @return void
     */
    public static function resetBz2SupportCache(): void
    {
        self::$bz2Supported = null;
    }
}
