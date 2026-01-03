<?php

/**
 * BigDump PHAR Web Entry Point
 *
 * This file handles web mode initialization for the PHAR build.
 * It adapts the logic from index.php for PHAR context with:
 * - External config file loading
 * - PHAR-aware path resolution
 * - Asset inlining mode
 *
 * @package BigDump
 * @author  w3spi5
 * @license MIT
 */

declare(strict_types=1);

use BigDump\Core\Application;
use BigDump\Core\PharContext;

// BIGDUMP_ROOT and BIGDUMP_PHAR_MODE already defined in stub

// Check MySQLi extension
if (!extension_loaded('mysqli')) {
    die('BigDump requires the MySQLi extension. Please enable it in your php.ini');
}

// Error handling
error_reporting(E_ALL);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function (Throwable $e): void {
    error_log("BigDump Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $version = Application::VERSION;

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BigDump Error</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
        .error-box { background: white; border-left: 4px solid #e74c3c; padding: 20px; max-width: 800px; margin: 50px auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 4px; }
        h1 { color: #e74c3c; margin-top: 0; }
        .details { color: #666; font-size: 14px; }
        pre { background: #f8f8f8; padding: 15px; overflow-x: auto; font-size: 12px; border-radius: 4px; }
        a { color: #3498db; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>BigDump Error</h1>
        <p><strong>{$message}</strong></p>
        <p class="details">BigDump v{$version} PHAR Mode</p>
    </div>
</body>
</html>
HTML;
});

// Get external root (directory containing the PHAR file)
$externalRoot = PharContext::getExternalRoot();

// Check for configuration file
$configFile = PharContext::getConfigPath();
$configExampleFile = $externalRoot . '/bigdump-config.example.php';

if (!file_exists($configFile)) {
    // Generate example config if it doesn't exist
    if (!file_exists($configExampleFile)) {
        $exampleContent = file_get_contents(BIGDUMP_ROOT . '/config/config.example.php');
        // Modify for PHAR context
        $exampleContent = str_replace(
            "'upload_dir' => '',",
            "'upload_dir' => './uploads/',",
            $exampleContent
        );
        file_put_contents($configExampleFile, $exampleContent);
    }

    // Display setup message
    $version = Application::VERSION;
    die(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BigDump Setup Required</title>
    <style>
        body { font-family: system-ui, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; margin: 0; display: flex; align-items: center; justify-content: center; }
        .setup-box { background: white; padding: 40px; max-width: 700px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border-radius: 12px; }
        h1 { color: #333; margin-top: 0; margin-bottom: 20px; }
        .step { background: #f8f9fa; padding: 15px 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #667eea; }
        .step strong { color: #667eea; }
        code { background: #e9ecef; padding: 2px 8px; border-radius: 4px; font-family: 'Fira Code', monospace; font-size: 14px; }
        .version { color: #888; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="setup-box">
        <h1>BigDump Setup Required</h1>
        <p>Welcome to BigDump! To get started, you need to create a configuration file.</p>

        <div class="step">
            <strong>Step 1:</strong> Copy the example configuration file:<br>
            <code>bigdump-config.example.php</code> &rarr; <code>bigdump-config.php</code>
        </div>

        <div class="step">
            <strong>Step 2:</strong> Edit <code>bigdump-config.php</code> and configure your database connection.
        </div>

        <div class="step">
            <strong>Step 3:</strong> Refresh this page to start using BigDump.
        </div>

        <p class="version">BigDump v{$version} PHAR Mode</p>
    </div>
</body>
</html>
HTML
    );
}

// Create uploads directory if it doesn't exist
$uploadsDir = PharContext::getUploadsPath();
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

// Create .htaccess for uploads protection
$htaccessFile = $uploadsDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    @file_put_contents($htaccessFile, <<<HTACCESS
# Deny direct access to dump files and temporary files
<FilesMatch "\.(sql|gz|csv|bz2|tmp)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
HTACCESS
    );
}

// Start PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Launch the application with PHAR-aware context
$app = new Application(BIGDUMP_ROOT, [
    'isPharMode' => true,
    'configPath' => $configFile,
    'uploadsPath' => $uploadsDir,
    'externalRoot' => $externalRoot,
]);
$app->run();
