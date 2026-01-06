<?php

/**
 * BigDump Configuration
 *
 * Modify this file to configure your MySQL import.
 * All options are documented below.
 *
 * @package BigDump
 * @author  w3spi5
 */

return [
    // =========================================================================
    // DATABASE CONFIGURATION (REQUIRED)
    // =========================================================================

    /**
     * MySQL server.
     * Format: 'hostname' or 'hostname:port' or 'localhost:/path/to/socket'
     */
    'db_server' => 'localhost',

    /**
     * Database name.
     */
    'db_name' => '',

    /**
     * MySQL username.
     */
    'db_username' => '',

    /**
     * MySQL password.
     */
    'db_password' => '',

    /**
     * Connection charset.
     * Must match the dump file charset.
     * Common values: 'utf8mb4', 'utf8', 'latin1', 'cp1251', 'koi8r'
     * See: https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html
     */
    'db_connection_charset' => 'utf8mb4',

    // =========================================================================
    // PERFORMANCE PROFILE (v2.19+)
    // =========================================================================

    /**
     * Performance profile selection.
     *
     * 'conservative' (default):
     *   - Targets 64MB PHP memory_limit
     *   - Safe for shared hosting environments
     *   - 64KB file buffer, 2000 INSERT batch size, 16MB max batch bytes
     *   - COMMIT after every batch
     *   - 5000 lines per session (v2.25+)
     *
     * 'aggressive':
     *   - Targets 128MB PHP memory_limit (REQUIRED: 128MB+ memory_limit)
     *   - +20-30% throughput improvement on INSERT-heavy dumps
     *   - 128KB file buffer, 5000 INSERT batch size, 32MB max batch bytes
     *   - COMMIT every 3 batches
     *   - 10000 lines per session (v2.25+)
     *
     * WARNING: If memory_limit < 128MB, aggressive mode automatically falls
     * back to conservative to prevent memory exhaustion. Check logs for warnings.
     */
    'performance_profile' => 'conservative',

    /**
     * Auto-aggressive mode threshold (v2.25+).
     *
     * Files larger than this threshold automatically use aggressive profile,
     * even if conservative is configured. This provides optimal performance
     * for large imports without requiring manual configuration changes.
     *
     * Set to 0 to disable auto-aggressive mode.
     * Default: 104857600 (100MB)
     *
     * Requirements:
     *   - PHP memory_limit >= 128MB (otherwise auto-upgrade is skipped)
     */
    'auto_profile_threshold' => 104857600, // 100MB

    /**
     * File read buffer size (in bytes).
     * Controls how much data is read from the SQL file per I/O operation.
     * Larger buffers reduce system calls but use more memory.
     *
     * Defaults (profile-dependent):
     *   - Conservative: 65536 (64KB)
     *   - Aggressive: 131072 (128KB)
     *
     * Valid range: 65536 (64KB) to 262144 (256KB)
     * Values outside this range are automatically clamped.
     *
     * Only override if you have specific I/O performance requirements.
     */
    // 'file_buffer_size' => 65536,

    /**
     * INSERT batch size (number of rows per batched INSERT).
     * Groups consecutive simple INSERTs into multi-value INSERTs.
     * Example: 2000 single INSERTs become 1 INSERT with 2000 value sets.
     *
     * Defaults (profile-dependent):
     *   - Conservative: 2000
     *   - Aggressive: 5000
     *
     * Higher values = faster imports, but more memory usage.
     * Set to 0 to disable INSERT batching entirely.
     */
    // 'insert_batch_size' => 2000,

    /**
     * Maximum batch size in bytes.
     * Prevents individual batched INSERT statements from exceeding this size.
     * Protects against MySQL max_allowed_packet limits and memory exhaustion.
     *
     * Defaults (profile-dependent):
     *   - Conservative: 16777216 (16MB)
     *   - Aggressive: 33554432 (32MB)
     *
     * Should not exceed your MySQL server's max_allowed_packet setting.
     */
    // 'max_batch_bytes' => 16777216,

    /**
     * COMMIT frequency (every N batches).
     * Controls how often COMMIT is issued during import.
     * Higher values reduce transaction overhead but increase rollback risk.
     *
     * Defaults (profile-dependent):
     *   - Conservative: 1 (COMMIT after every batch)
     *   - Aggressive: 3 (COMMIT every 3 batches)
     */
    // 'commit_frequency' => 1,

    // =========================================================================
    // IMPORT CONFIGURATION (OPTIONAL)
    // =========================================================================

    /**
     * Dump filename to import.
     * Leave empty to display the list of available files.
     */
    'filename' => '',

    /**
     * AJAX mode.
     * true: Import without page refresh (recommended)
     * false: Import with classic refresh
     */
    'ajax' => true,

    /**
     * Number of lines to process per session (base value).
     *
     * v2.25+ defaults (profile-dependent):
     *   - Conservative: 5000 (increased from 3000)
     *   - Aggressive: 10000 (increased from 5000)
     *
     * With auto-tuning enabled (default), this is dynamically adjusted
     * based on available RAM (NVMe-optimized profiles):
     *   < 512 MB  ->  10,000 lines
     *   < 1 GB    ->  30,000 lines
     *   < 2 GB    ->  60,000 lines
     *   < 4 GB    -> 100,000 lines
     *   < 8 GB    -> 150,000 lines
     *   > 8 GB    -> 200,000 lines
     */
    'linespersession' => 5000,

    /**
     * Force a specific batch size (bypasses auto-tuning).
     * Set to 0 to use auto-tuning (recommended).
     * Use this only if you know your server can handle it.
     * Example: 100000 for very powerful servers.
     */
    'force_batch_size' => 0,

    /**
     * Delay in milliseconds between each session.
     * Use to reduce server load (0 = no delay).
     */
    'delaypersession' => 0,

    // =========================================================================
    // CSV CONFIGURATION (OPTIONAL - only for .csv files)
    // =========================================================================

    /**
     * Destination table for CSV files.
     * REQUIRED if you import a CSV file.
     */
    'csv_insert_table' => '',

    /**
     * Empty the table before CSV import.
     */
    'csv_preempty_table' => false,

    /**
     * CSV field delimiter.
     */
    'csv_delimiter' => ',',

    /**
     * CSV field enclosure character.
     */
    'csv_enclosure' => '"',

    /**
     * Add quotes around CSV values.
     * Set to false if your CSV data already has quotes.
     */
    'csv_add_quotes' => true,

    /**
     * Add escape slashes for CSV.
     * Set to false if your CSV data is already escaped.
     */
    'csv_add_slashes' => true,

    // =========================================================================
    // ADVANCED CONFIGURATION (OPTIONAL)
    // =========================================================================

    /**
     * SQL comment markers.
     * Lines starting with these strings will be ignored.
     */
    'comment_markers' => [
        '#',
        '-- ',
        'DELIMITER',
        '/*!',
        // Uncomment if needed:
        // '---',           // For some proprietary dumps
        // 'CREATE DATABASE', // To ignore CREATE DATABASE
    ],

    /**
     * SQL queries to execute at the beginning of each session.
     * Recommended for large imports (significant speed boost).
     */
    'pre_queries' => [
        'SET autocommit = 0',
        'SET unique_checks = 0',
        'SET foreign_key_checks = 0',
        'SET sql_log_bin = 0',  // Disable binary logging for speed
    ],

    /**
     * SQL queries to execute after import completion.
     * Restores normal database constraints.
     */
    'post_queries' => [
        'COMMIT',
        'SET autocommit = 1',
        'SET unique_checks = 1',
        'SET foreign_key_checks = 1',
    ],

    /**
     * Default query end delimiter.
     * Can be modified by DELIMITER in the dump.
     */
    'delimiter' => ';',

    /**
     * SQL string quote character.
     * Change to '"' if your dump uses double quotes.
     */
    'string_quotes' => "'",

    /**
     * Maximum number of lines per SQL query.
     * Increase if you have very long queries (extended inserts, stored procedures).
     * mysqldump with --extended-insert can produce very long INSERT statements.
     */
    'max_query_lines' => 10000,

    /**
     * Uploaded files directory.
     * Leave empty to use the default 'uploads' directory.
     */
    'upload_dir' => '',

    /**
     * Test mode.
     * true: Reads the file without executing SQL queries.
     * Useful to verify that the file is readable.
     */
    'test_mode' => false,

    /**
     * Debug mode.
     * true: Displays detailed error traces.
     */
    'debug' => false,

    /**
     * Read buffer size (in bytes).
     * Only modify if you have memory issues.
     */
    'data_chunk_length' => 16384,

    /**
     * Allowed file extensions.
     */
    'allowed_extensions' => ['sql', 'gz', 'csv'],

    /**
     * Maximum memory size for a query (in bytes).
     * Protection against infinite queries (100 MB for high-speed imports).
     */
    'max_query_memory' => 104857600,

    // =========================================================================
    // PERFORMANCE OPTIMIZATION (NVMe/SSD)
    // =========================================================================

    /**
     * Maximum batch size for auto-tuner (lines per session).
     * NVMe SSD can handle 300,000+ lines per session.
     */
    'max_batch_size' => 300000,

    /**
     * Minimum batch size for auto-tuner.
     * v2.25: Increased from 3000 to 5000 for better performance.
     */
    'min_batch_size' => 5000,

    // =========================================================================
    // FILE-AWARE TUNING (v2.16+)
    // =========================================================================

    /**
     * Enable file-aware auto-tuning.
     * When enabled, analyzes the SQL file to determine optimal batch sizes
     * based on file size category and content type (bulk INSERTs, etc.).
     * Provides x2-3 speedup for large files by utilizing more RAM.
     */
    'file_aware_tuning' => true,

    /**
     * Sample size for file analysis (in bytes).
     * Larger samples give more accurate estimates but take longer.
     * 1MB is optimal for most files.
     */
    'sample_size_bytes' => 1048576,

    /**
     * Minimum batch size for dynamic adaptation.
     * Prevents batch from getting too small during adaptation.
     */
    'min_dynamic_batch' => 50000,
];
