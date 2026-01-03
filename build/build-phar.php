<?php

/**
 * BigDump PHAR Build Script
 *
 * Creates a standalone PHAR file that works in both web and CLI modes.
 * The PHAR includes all PHP files, templates, and assets for inlining.
 *
 * Usage: php -d phar.readonly=0 build/build-phar.php
 *
 * @package BigDump
 * @author  w3spi5
 * @license MIT
 */

declare(strict_types=1);

// Exit codes (matching cli.php pattern)
define('EXIT_SUCCESS', 0);
define('EXIT_USER_ERROR', 1);
define('EXIT_RUNTIME_ERROR', 2);

// Paths
define('BUILD_DIR', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));
define('OUTPUT_DIR', PROJECT_ROOT . '/dist');
define('OUTPUT_FILE', OUTPUT_DIR . '/bigdump.phar');
define('CONFIG_EXAMPLE_OUTPUT', OUTPUT_DIR . '/bigdump-config.example.php');

/**
 * Output error message to STDERR and exit
 *
 * @param string $message Error message
 * @param int $exitCode Exit code
 */
function exitWithError(string $message, int $exitCode = EXIT_USER_ERROR): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit($exitCode);
}

/**
 * Output info message to STDOUT
 *
 * @param string $message Message to output
 */
function info(string $message): void
{
    echo $message . "\n";
}

/**
 * Format bytes to human readable format
 *
 * @param int $bytes Number of bytes
 * @return string Formatted size
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[(int) $pow];
}

/**
 * Recursively collect PHP files from a directory
 *
 * @param string $dir Directory to scan
 * @param string $baseDir Base directory for relative paths
 * @return array<string, string> Map of relative path => absolute path
 */
function collectPhpFiles(string $dir, string $baseDir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
            // Exclude CLAUDE.md and test files
            if (strpos($relativePath, 'tests/') === 0) {
                continue;
            }
            $files[$relativePath] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Collect template files
 *
 * @param string $dir Templates directory
 * @param string $baseDir Base directory for relative paths
 * @return array<string, string> Map of relative path => absolute path
 */
function collectTemplates(string $dir, string $baseDir): array
{
    $files = [];

    if (!is_dir($dir)) {
        return $files;
    }

    foreach (glob($dir . '/*.php') as $file) {
        $relativePath = str_replace($baseDir . '/', '', $file);
        $files[$relativePath] = $file;
    }

    // Include partials subdirectory if exists
    $partialsDir = $dir . '/partials';
    if (is_dir($partialsDir)) {
        foreach (glob($partialsDir . '/*.php') as $file) {
            $relativePath = str_replace($baseDir . '/', '', $file);
            $files[$relativePath] = $file;
        }
    }

    return $files;
}

/**
 * Collect asset files for inlining
 *
 * @return array<string, string> Map of relative path => file content
 */
function collectAssets(): array
{
    $assets = [];
    $assetsDir = PROJECT_ROOT . '/assets';

    // CSS
    $cssFile = $assetsDir . '/dist/app.min.css';
    if (file_exists($cssFile)) {
        $assets['assets/dist/app.min.css'] = file_get_contents($cssFile);
    }

    // JavaScript files
    $jsFiles = glob($assetsDir . '/dist/*.min.js');
    foreach ($jsFiles as $jsFile) {
        $relativePath = 'assets/dist/' . basename($jsFile);
        $assets[$relativePath] = file_get_contents($jsFile);
    }

    // SVG icons
    $iconsFile = $assetsDir . '/icons.svg';
    if (file_exists($iconsFile)) {
        $assets['assets/icons.svg'] = file_get_contents($iconsFile);
    }

    return $assets;
}

/**
 * Generate the PHAR stub with dual-mode detection
 *
 * @param string $version Application version
 * @return string PHAR stub code
 */
function generateStub(string $version): string
{
    return <<<'STUB'
<?php
/**
 * BigDump PHAR Stub - Dual-mode entry point
 *
 * Automatically detects web vs CLI mode and routes to appropriate handler.
 */

// PHP version check
if (PHP_VERSION_ID < 80100) {
    $mode = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' ? 'cli' : 'web';
    $message = 'BigDump requires PHP 8.1 or higher. You have PHP ' . PHP_VERSION;
    if ($mode === 'cli') {
        fwrite(STDERR, "Error: {$message}\n");
        exit(1);
    } else {
        die($message);
    }
}

// Define PHAR root
define('BIGDUMP_ROOT', 'phar://' . __FILE__);
define('BIGDUMP_PHAR_MODE', true);

// PSR-4 autoloader for PHAR context
spl_autoload_register(function (string $class): void {
    $prefix = 'BigDump\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BIGDUMP_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Detect execution mode
$sapi = php_sapi_name();
$cliSapis = ['cli', 'cli-server', 'phpdbg'];
$isCliMode = in_array($sapi, $cliSapis, true);

if ($isCliMode) {
    // CLI mode - route to CLI entry point
    require BIGDUMP_ROOT . '/build/stubs/cli-entry.php';
} else {
    // Web mode - route to web entry point
    require BIGDUMP_ROOT . '/build/stubs/web-entry.php';
}

__HALT_COMPILER();
STUB;
}

/**
 * Generate the example config file for distribution
 *
 * @return string Config file content
 */
function generateExampleConfig(): string
{
    return <<<'CONFIG'
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
CONFIG;
}

// ============================================================================
// MAIN BUILD PROCESS
// ============================================================================

info("BigDump PHAR Builder");
info("====================");
info("");

// Validate phar.readonly setting
if (ini_get('phar.readonly')) {
    exitWithError(
        "phar.readonly is enabled. Run with: php -d phar.readonly=0 " . basename(__FILE__),
        EXIT_USER_ERROR
    );
}

// Validate PHP version
if (PHP_VERSION_ID < 80100) {
    exitWithError("PHP 8.1 or higher is required. You have PHP " . PHP_VERSION);
}

// Load Application class to get version
require_once PROJECT_ROOT . '/src/Core/Application.php';
$version = \BigDump\Core\Application::VERSION;

info("Building BigDump PHAR v{$version}");
info("");

// Create output directory if needed
if (!is_dir(OUTPUT_DIR)) {
    if (!mkdir(OUTPUT_DIR, 0755, true)) {
        exitWithError("Failed to create output directory: " . OUTPUT_DIR, EXIT_RUNTIME_ERROR);
    }
}

// Remove existing PHAR if present
if (file_exists(OUTPUT_FILE)) {
    unlink(OUTPUT_FILE);
}

try {
    // Create PHAR
    $phar = new Phar(OUTPUT_FILE, 0, 'bigdump.phar');
    $phar->startBuffering();

    // Set stub
    $phar->setStub(generateStub($version));

    // Collect and add PHP files (src directory)
    info("Collecting PHP files from src/...");
    $srcFiles = collectPhpFiles(PROJECT_ROOT . '/src', PROJECT_ROOT);
    $srcCount = count($srcFiles);
    $srcSize = 0;

    foreach ($srcFiles as $relativePath => $absolutePath) {
        $content = file_get_contents($absolutePath);
        $srcSize += strlen($content);
        $phar->addFromString($relativePath, $content);
    }
    info("  - Added {$srcCount} PHP files (" . formatBytes($srcSize) . ")");

    // Collect and add templates
    info("Collecting templates...");
    $templateFiles = collectTemplates(PROJECT_ROOT . '/templates', PROJECT_ROOT);
    $templateCount = count($templateFiles);
    $templateSize = 0;

    foreach ($templateFiles as $relativePath => $absolutePath) {
        $content = file_get_contents($absolutePath);
        $templateSize += strlen($content);
        $phar->addFromString($relativePath, $content);
    }
    info("  - Added {$templateCount} templates (" . formatBytes($templateSize) . ")");

    // Collect and add assets (uncompressed for runtime inlining)
    info("Collecting assets for inlining...");
    $assets = collectAssets();
    $assetCount = count($assets);
    $assetSize = 0;

    foreach ($assets as $relativePath => $content) {
        $assetSize += strlen($content);
        $phar->addFromString($relativePath, $content);
    }
    info("  - Added {$assetCount} asset files (" . formatBytes($assetSize) . ")");

    // Add entry point stubs
    info("Adding entry point stubs...");
    $webEntry = BUILD_DIR . '/stubs/web-entry.php';
    $cliEntry = BUILD_DIR . '/stubs/cli-entry.php';

    if (file_exists($webEntry)) {
        $phar->addFromString('build/stubs/web-entry.php', file_get_contents($webEntry));
    } else {
        exitWithError("Web entry stub not found: {$webEntry}", EXIT_RUNTIME_ERROR);
    }

    if (file_exists($cliEntry)) {
        $phar->addFromString('build/stubs/cli-entry.php', file_get_contents($cliEntry));
    } else {
        exitWithError("CLI entry stub not found: {$cliEntry}", EXIT_RUNTIME_ERROR);
    }

    // Compress PHP files (not assets)
    info("Compressing PHP files with GZ...");
    foreach ($phar as $file) {
        $path = $file->getPathname();
        // Only compress PHP files, not assets
        if (str_ends_with($path, '.php')) {
            if ($file->isCompressed() === false && extension_loaded('zlib')) {
                $file->compress(Phar::GZ);
            }
        }
    }

    $phar->stopBuffering();

    // Generate example config
    info("Generating example config file...");
    file_put_contents(CONFIG_EXAMPLE_OUTPUT, generateExampleConfig());

    // Build summary
    info("");
    info("=== Build Summary ===");
    info("PHAR file: " . OUTPUT_FILE);
    info("Config example: " . CONFIG_EXAMPLE_OUTPUT);
    info("");
    info("Contents:");
    info("  - PHP files: {$srcCount} (" . formatBytes($srcSize) . " uncompressed)");
    info("  - Templates: {$templateCount} (" . formatBytes($templateSize) . ")");
    info("  - Assets: {$assetCount} (" . formatBytes($assetSize) . ")");
    info("");

    $pharSize = filesize(OUTPUT_FILE);
    $configSize = filesize(CONFIG_EXAMPLE_OUTPUT);

    info("Output files:");
    info("  - bigdump.phar: " . formatBytes($pharSize));
    info("  - bigdump-config.example.php: " . formatBytes($configSize));
    info("");
    info("Build completed successfully!");

    exit(EXIT_SUCCESS);

} catch (Throwable $e) {
    exitWithError("Build failed: " . $e->getMessage(), EXIT_RUNTIME_ERROR);
}
