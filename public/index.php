<?php

/**
 * BigDump 2.0 - Point d'entrée principal
 *
 * Ce fichier est le point d'entrée de l'application BigDump refactorisée.
 * Il initialise l'autoloader et lance l'application MVC.
 *
 * UTILISATION:
 * 1. Configurez config/config.php avec vos identifiants de base de données
 * 2. Uploadez vos fichiers dump (.sql, .gz, .csv) dans le dossier uploads/
 * 3. Accédez à ce fichier via votre navigateur web
 *
 * @package BigDump
 * @version 2.0.0
 * @author  Refactorisation MVC
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Définir le répertoire racine de l'application
define('BIGDUMP_ROOT', dirname(__DIR__));

// Vérifier la version PHP
if (PHP_VERSION_ID < 80100) {
    die('BigDump 2.0 requires PHP 8.1 or higher. You have PHP ' . PHP_VERSION);
}

// Vérifier l'extension MySQLi
if (!extension_loaded('mysqli')) {
    die('BigDump requires the MySQLi extension. Please enable it in your php.ini');
}

// Autoloader simple (sans Composer)
spl_autoload_register(function (string $class): void {
    // Vérifier que la classe est dans notre namespace
    $prefix = 'BigDump\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    // Construire le chemin du fichier
    $relativeClass = substr($class, strlen($prefix));
    $file = BIGDUMP_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Gestion des erreurs
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

// Créer le répertoire uploads s'il n'existe pas
$uploadsDir = BIGDUMP_ROOT . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

// Créer un fichier .htaccess pour protéger le répertoire uploads
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

// Lancer l'application
use BigDump\Core\Application;

$app = new Application(BIGDUMP_ROOT);
$app->run();
