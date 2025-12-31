<?php

/**
 * BigDump CLI SQL Optimizer
 *
 * Standalone CLI tool that rewrites SQL dump files with INSERT batching
 * optimization, without requiring a database connection.
 *
 * Usage: php cli.php <input-file> --output <output-file> [options]
 *
 * @package BigDump
 * @author  w3spi5
 * @license MIT
 */

declare(strict_types=1);

// Define the application root directory
define('BIGDUMP_ROOT', __DIR__);

// Exit codes
define('EXIT_SUCCESS', 0);
define('EXIT_USER_ERROR', 1);
define('EXIT_RUNTIME_ERROR', 2);

// Check PHP version
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Error: BigDump CLI requires PHP 8.1 or higher. You have PHP " . PHP_VERSION . "\n");
    exit(EXIT_USER_ERROR);
}

// Simple autoloader (PSR-4 pattern from index.php)
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

// Profile defaults (extracted from Config.php)
const PROFILE_DEFAULTS = [
    'conservative' => [
        'batch_size' => 2000,
        'max_batch_bytes' => 16777216,  // 16MB
    ],
    'aggressive' => [
        'batch_size' => 5000,
        'max_batch_bytes' => 33554432,  // 32MB
    ],
];

/**
 * Display help information
 */
function displayHelp(): void
{
    $help = <<<HELP
BigDump SQL Optimizer - CLI Tool

Usage: php cli.php <input-file> --output <output-file> [options]

Arguments:
  input-file              SQL dump file to optimize (.sql, .sql.gz, .sql.bz2)

Required Options:
  -o, --output <file>     Output file path (must be .sql)

Optional Options:
  --batch-size=<n>        INSERT batch size (default: profile-based)
  --profile=<name>        Performance profile: conservative|aggressive
                          (default: conservative)
  -f, --force             Overwrite output file if it exists
  -h, --help              Display this help message

Examples:
  php cli.php dump.sql -o optimized.sql
  php cli.php dump.sql.gz --output optimized.sql --batch-size=5000
  php cli.php backup.sql.bz2 -o backup_batched.sql --profile=aggressive -f

Profile Defaults:
  conservative: batch-size=2000, max-batch-bytes=16MB
  aggressive:   batch-size=5000, max-batch-bytes=32MB

Exit Codes:
  0 - Success
  1 - User error (invalid arguments, file not found)
  2 - Runtime error (processing failure)

HELP;
    echo $help;
}

/**
 * Output error message to STDERR and exit
 *
 * @param string $message Error message
 * @param int $exitCode Exit code
 */
function exitWithError(string $message, int $exitCode = EXIT_USER_ERROR): never
{
    fwrite(STDERR, "Error: {$message}\n");
    fwrite(STDERR, "Use --help for usage information.\n");
    exit($exitCode);
}

/**
 * Parse command-line arguments manually
 *
 * @param array<int, string> $argv Command line arguments
 * @return array{input: string, output: string, batch_size: int|null, profile: string, force: bool}
 */
function parseArguments(array $argv): array
{
    // Remove script name
    $scriptName = array_shift($argv);

    // Default values
    $inputFile = '';
    $outputFile = null;
    $profile = 'conservative';
    $batchSize = null;
    $force = false;

    // Process arguments
    $i = 0;
    $argc = count($argv);

    while ($i < $argc) {
        $arg = $argv[$i];

        // Check for help
        if ($arg === '--help' || $arg === '-h') {
            displayHelp();
            exit(EXIT_SUCCESS);
        }

        // Check for output option
        if ($arg === '-o' || $arg === '--output') {
            $i++;
            if ($i >= $argc) {
                exitWithError("Option {$arg} requires a value.");
            }
            $outputFile = $argv[$i];
            $i++;
            continue;
        }

        // Check for --output=value format
        if (str_starts_with($arg, '--output=')) {
            $outputFile = substr($arg, 9);
            $i++;
            continue;
        }

        // Check for profile option
        if ($arg === '--profile' || $arg === '-p') {
            $i++;
            if ($i >= $argc) {
                exitWithError("Option {$arg} requires a value.");
            }
            $profile = $argv[$i];
            $i++;
            continue;
        }

        // Check for --profile=value format
        if (str_starts_with($arg, '--profile=')) {
            $profile = substr($arg, 10);
            $i++;
            continue;
        }

        // Check for batch-size option
        if ($arg === '--batch-size') {
            $i++;
            if ($i >= $argc) {
                exitWithError("Option {$arg} requires a value.");
            }
            $batchSize = $argv[$i];
            $i++;
            continue;
        }

        // Check for --batch-size=value format
        if (str_starts_with($arg, '--batch-size=')) {
            $batchSize = substr($arg, 13);
            $i++;
            continue;
        }

        // Check for force flag
        if ($arg === '-f' || $arg === '--force') {
            $force = true;
            $i++;
            continue;
        }

        // If it starts with -, it's an unknown option
        if (str_starts_with($arg, '-')) {
            exitWithError("Unknown option: {$arg}");
        }

        // Otherwise, it's the input file (first positional argument)
        if (empty($inputFile)) {
            $inputFile = $arg;
        }

        $i++;
    }

    // Validate required arguments
    if (empty($inputFile)) {
        exitWithError("Missing input file. Please provide a SQL dump file to optimize.");
    }

    if ($outputFile === null) {
        exitWithError("Missing required --output (-o) option.");
    }

    // Validate profile
    if (!in_array($profile, ['conservative', 'aggressive'], true)) {
        exitWithError("Invalid profile '{$profile}'. Valid options: conservative, aggressive");
    }

    // Validate batch size
    $parsedBatchSize = null;
    if ($batchSize !== null) {
        $parsedBatchSize = filter_var($batchSize, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsedBatchSize === false) {
            exitWithError("Invalid batch-size value. Must be a positive integer.");
        }
    }

    return [
        'input' => $inputFile,
        'output' => $outputFile,
        'batch_size' => $parsedBatchSize,
        'profile' => $profile,
        'force' => $force,
    ];
}

/**
 * Validate input file
 *
 * @param string $inputFile Input file path
 */
function validateInputFile(string $inputFile): void
{
    if (!file_exists($inputFile)) {
        exitWithError("Input file not found: {$inputFile}");
    }

    if (!is_readable($inputFile)) {
        exitWithError("Input file is not readable: {$inputFile}");
    }

    // Validate extension
    $extension = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));

    // Handle double extensions like .sql.gz
    $basename = basename($inputFile);
    if (str_ends_with(strtolower($basename), '.sql.gz')) {
        $extension = 'gz';
    } elseif (str_ends_with(strtolower($basename), '.sql.bz2')) {
        $extension = 'bz2';
    }

    $validExtensions = ['sql', 'gz', 'bz2'];
    if (!in_array($extension, $validExtensions, true)) {
        exitWithError("Unsupported file type. Supported extensions: .sql, .sql.gz, .sql.bz2");
    }

    // Check bz2 extension availability
    if ($extension === 'bz2' && !function_exists('bzopen')) {
        exitWithError("BZip2 files require the PHP bz2 extension which is not installed.");
    }
}

/**
 * Validate output file
 *
 * @param string $outputFile Output file path
 * @param bool $force Whether to allow overwriting
 */
function validateOutputFile(string $outputFile, bool $force): void
{
    // Check if output file exists
    if (file_exists($outputFile) && !$force) {
        exitWithError("Output file already exists: {$outputFile}\nUse --force (-f) to overwrite.");
    }

    // Check if output directory is writable
    $outputDir = dirname($outputFile);
    if ($outputDir === '') {
        $outputDir = '.';
    }

    if (!is_dir($outputDir)) {
        exitWithError("Output directory does not exist: {$outputDir}");
    }

    if (!is_writable($outputDir)) {
        exitWithError("Output directory is not writable: {$outputDir}");
    }
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

// Parse arguments
$args = parseArguments($argv);

// Validate input file
validateInputFile($args['input']);

// Validate output file
validateOutputFile($args['output'], $args['force']);

// Get profile settings
$profileSettings = PROFILE_DEFAULTS[$args['profile']];
$batchSize = $args['batch_size'] ?? $profileSettings['batch_size'];
$maxBatchBytes = $profileSettings['max_batch_bytes'];

// Import CLI optimizer service
use BigDump\Services\CliOptimizerService;

try {
    $optimizer = new CliOptimizerService(
        $args['input'],
        $args['output'],
        [
            'batchSize' => $batchSize,
            'maxBatchBytes' => $maxBatchBytes,
            'force' => $args['force'],
            'profile' => $args['profile'],
        ]
    );

    $result = $optimizer->run();

    exit(EXIT_SUCCESS);
} catch (Throwable $e) {
    fwrite(STDERR, "Runtime Error: " . $e->getMessage() . "\n");
    exit(EXIT_RUNTIME_ERROR);
}
