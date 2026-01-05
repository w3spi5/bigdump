<?php

/**
 * BigDump PHAR Configuration
 *
 * Copy this file to bigdump-config.php (in the same directory as bigdump.phar)
 * and modify the values below.
 *
 * @package BigDump
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
     */
    'db_connection_charset' => 'utf8mb4',

    // =========================================================================
    // PERFORMANCE PROFILE
    // =========================================================================

    /**
     * Performance profile selection.
     * 'conservative' (default): Safe for shared hosting, 64MB memory
     * 'aggressive': For dedicated servers, requires 128MB+ memory
     */
    'performance_profile' => 'conservative',

    // =========================================================================
    // UPLOAD DIRECTORY
    // =========================================================================

    /**
     * Upload directory for SQL dump files.
     * Path is relative to the bigdump.phar location.
     * Leave empty to use default 'uploads/' directory.
     */
    'upload_dir' => './uploads/',

    // =========================================================================
    // IMPORT CONFIGURATION
    // =========================================================================

    /**
     * AJAX mode (true = no page refresh during import).
     */
    'ajax' => true,

    /**
     * Number of lines to process per session.
     */
    'linespersession' => 3000,

    /**
     * Debug mode (shows detailed error traces).
     */
    'debug' => false,
];