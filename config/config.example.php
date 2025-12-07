<?php

/**
 * BigDump 2.5 - Configuration
 *
 * Modify this file to configure your MySQL import.
 * All options are documented below.
 *
 * @package BigDump
 * @version 2.5
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
     * With auto-tuning enabled (default), this is dynamically adjusted
     * based on available RAM:
     *   < 512 MB  →  5,000 lines
     *   < 1 GB    → 15,000 lines
     *   < 2 GB    → 30,000 lines
     *   < 4 GB    → 50,000 lines
     *   > 4 GB    → 80,000 lines
     */
    'linespersession' => 3000,

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
     * Useful to disable foreign key checks.
     */
    'pre_queries' => [
        // Uncomment if needed:
        // 'SET foreign_key_checks = 0',
        // 'SET unique_checks = 0',
        // 'SET autocommit = 0',
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
     * Protection against infinite queries (10 MB by default).
     */
    'max_query_memory' => 10485760,
];
