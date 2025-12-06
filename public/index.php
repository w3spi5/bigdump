<?php

/**
 * BigDump 2.5 - Main entry point
 *
 * This file is the entry point of the refactored BigDump application.
 * It initializes the autoloader and launches the MVC application.
 *
 * USAGE:
 * 1. Configure config/config.php with your database credentials
 * 2. Upload your dump files (.sql, .gz, .csv) to the uploads/ directory
 * 3. Access this file via your web browser
 *
 * @package BigDump
 * @version 2.5
 * @author  MVC Refactoring
 * @license MIT
 */

declare(strict_types=1);

// Define the application root directory
define('BIGDUMP_ROOT', dirname(__DIR__));

// Check PHP version
if (PHP_VERSION_ID < 80100) {
    die('BigDump 2.5 requires PHP 8.1 or higher. You have PHP ' . PHP_VERSION);
}

// Check MySQLi extension
if (!extension_loaded('mysqli')) {
    die('BigDump requires the MySQLi extension. Please enable it in your php.ini');
}

// Simple autoloader (without Composer)
spl_autoload_register(function (string $class): void {
    // Check that the class is in our namespace
    $prefix = 'BigDump\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    // Build the file path
    $relativeClass = substr($class, strlen($prefix));
    $file = BIGDUMP_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

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

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BigDump Error</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 40px; }
        .error { background: white; border-left: 4px solid #e74c3c; padding: 20px; max-width: 600px; margin: 0 auto; }
        h1 { color: #e74c3c; margin-top: 0; font-size: 20px; }
        p { color: #333; line-height: 1.6; }
        code { background: #f8f8f8; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="error">
        <h1>BigDump Error</h1>
        <p>{$message}</p>
        <p>Please check your configuration and try again.</p>
    </div>
</body>
</html>
HTML;
});

// Create the uploads directory if it doesn't exist
$uploadsDir = BIGDUMP_ROOT . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

// Create an .htaccess file to protect the uploads directory
$htaccessFile = $uploadsDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    @file_put_contents($htaccessFile, <<<HTACCESS
# Deny direct access to dump files
<FilesMatch "\.(sql|gz|csv)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
HTACCESS
    );
}

// Start PHP session for cross-request state persistence
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Launch the application
use BigDump\Core\Application;

$app = new Application(BIGDUMP_ROOT);
$app->run();
