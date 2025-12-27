<?php

/**
 * Config and File Listing BZ2 Support Tests
 *
 * Tests for the Config BZ2 extension support introduced for .bz2 file handling.
 * These tests verify:
 * - 'bz2' in allowed_extensions array
 * - isExtensionAllowed('bz2') returns true
 * - listFiles() includes .bz2 files when ext-bz2 available
 * - listFiles() excludes .bz2 files when ext-bz2 missing
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';

use BigDump\Config\Config;
use BigDump\Models\FileHandler;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class ConfigBz2TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    /** @var array<string, string> */
    private array $failures = [];
    /** @var array<string> */
    private array $skippedTests = [];

    public function test(string $name, callable $testFn): void
    {
        try {
            $testFn();
            $this->passed++;
            echo "  PASS: {$name}\n";
        } catch (SkipConfigTestException $e) {
            $this->skipped++;
            $this->skippedTests[] = $name . ': ' . $e->getMessage();
            echo "  SKIP: {$name} - {$e->getMessage()}\n";
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[$name] = $e->getMessage();
            echo "  FAIL: {$name}\n";
            echo "        " . $e->getMessage() . "\n";
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new RuntimeException(
                $message ?: "Expected {$expectedStr}, got {$actualStr}"
            );
        }
    }

    public function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new RuntimeException($message ?: "Expected true, got false");
        }
    }

    public function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new RuntimeException($message ?: "Expected false, got true");
        }
    }

    public function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            throw new RuntimeException(
                $message ?: "Expected array to contain " . var_export($needle, true)
            );
        }
    }

    public function assertNotContains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (in_array($needle, $haystack, true)) {
            throw new RuntimeException(
                $message ?: "Expected array NOT to contain " . var_export($needle, true)
            );
        }
    }

    public function skip(string $reason): void
    {
        throw new SkipConfigTestException($reason);
    }

    public function summary(): int
    {
        echo "\n";
        echo "==========================================\n";
        echo "Tests: " . ($this->passed + $this->failed + $this->skipped) . ", ";
        echo "Passed: {$this->passed}, ";
        echo "Failed: {$this->failed}, ";
        echo "Skipped: {$this->skipped}\n";
        echo "==========================================\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $name => $message) {
                echo "  - {$name}: {$message}\n";
            }
        }

        if ($this->skipped > 0) {
            echo "\nSkipped:\n";
            foreach ($this->skippedTests as $skipInfo) {
                echo "  - {$skipInfo}\n";
            }
        }

        return $this->failed > 0 ? 1 : 0;
    }
}

/**
 * Exception for skipping tests
 */
class SkipConfigTestException extends Exception {}

/**
 * Creates a temporary config file with given settings
 *
 * @param array<string, mixed> $config
 * @return string Path to temporary config file
 */
function createTempConfigBz2Config(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_config_bz2_test_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupTempConfigBz2Config(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Creates a temporary directory with test files
 *
 * @param array<string> $filenames Filenames to create
 * @return string Path to temporary directory
 */
function createTempDirWithFiles(array $filenames): string
{
    $tempDir = sys_get_temp_dir() . '/bigdump_config_bz2_test_uploads_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    foreach ($filenames as $filename) {
        $filepath = $tempDir . '/' . $filename;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'bz2' && function_exists('bzopen')) {
            // Create actual bz2 file
            $bz = bzopen($filepath, 'w');
            bzwrite($bz, "-- Test SQL content\n");
            bzclose($bz);
        } elseif ($extension === 'gz' && function_exists('gzopen')) {
            // Create actual gz file
            $gz = gzopen($filepath, 'wb9');
            gzwrite($gz, "-- Test SQL content\n");
            gzclose($gz);
        } else {
            // Create regular file
            file_put_contents($filepath, "-- Test SQL content\n");
        }
    }

    return $tempDir;
}

/**
 * Cleans up temporary test directory
 */
function cleanupTempConfigDir(string $dir): void
{
    if (is_dir($dir) && strpos($dir, 'bigdump_config_bz2_test_uploads') !== false) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}

// ============================================================================
// TEST SUITE: Config and File Listing BZ2 Support
// ============================================================================

echo "Config and File Listing BZ2 Support Tests\n";
echo "==========================================\n\n";

$runner = new ConfigBz2TestRunner();

// Check if BZ2 extension is available
$bz2Available = function_exists('bzopen');
echo "BZ2 Extension Available: " . ($bz2Available ? "YES" : "NO") . "\n\n";

// ----------------------------------------------------------------------------
// Test 1: 'bz2' in allowed_extensions array
// ----------------------------------------------------------------------------
$runner->test("'bz2' is in default allowed_extensions array", function () use ($runner) {
    $configFile = createTempConfigBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        // Get allowed_extensions from config
        $allowedExtensions = $config->get('allowed_extensions');

        $runner->assertContains('bz2', $allowedExtensions,
            "Default allowed_extensions should include 'bz2'");

        // Also verify other expected extensions are present
        $runner->assertContains('sql', $allowedExtensions,
            "Default allowed_extensions should include 'sql'");
        $runner->assertContains('gz', $allowedExtensions,
            "Default allowed_extensions should include 'gz'");
        $runner->assertContains('csv', $allowedExtensions,
            "Default allowed_extensions should include 'csv'");
    } finally {
        cleanupTempConfigBz2Config($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: isExtensionAllowed('bz2') returns true
// ----------------------------------------------------------------------------
$runner->test("isExtensionAllowed('bz2') returns true", function () use ($runner) {
    $configFile = createTempConfigBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        // Test isExtensionAllowed for bz2
        $runner->assertTrue($config->isExtensionAllowed('bz2'),
            "isExtensionAllowed('bz2') should return true");

        // Test case-insensitivity
        $runner->assertTrue($config->isExtensionAllowed('BZ2'),
            "isExtensionAllowed('BZ2') should return true (case-insensitive)");
        $runner->assertTrue($config->isExtensionAllowed('Bz2'),
            "isExtensionAllowed('Bz2') should return true (case-insensitive)");

        // Verify other extensions still work
        $runner->assertTrue($config->isExtensionAllowed('sql'),
            "isExtensionAllowed('sql') should return true");
        $runner->assertTrue($config->isExtensionAllowed('gz'),
            "isExtensionAllowed('gz') should return true");
        $runner->assertTrue($config->isExtensionAllowed('csv'),
            "isExtensionAllowed('csv') should return true");

        // Verify invalid extension returns false
        $runner->assertFalse($config->isExtensionAllowed('exe'),
            "isExtensionAllowed('exe') should return false");
    } finally {
        cleanupTempConfigBz2Config($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: listFiles() includes .bz2 files when ext-bz2 available
// ----------------------------------------------------------------------------
$runner->test("listFiles() includes .bz2 files when ext-bz2 available", function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available - cannot test inclusion');
    }

    // Create temp directory with various files
    $tempDir = createTempDirWithFiles([
        'test1.sql',
        'test2.gz',
        'test3.bz2',
        'test4.sql.bz2',
        'test5.csv',
    ]);

    $configFile = createTempConfigBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Get file list
        $files = $fileHandler->listFiles();
        $filenames = array_column($files, 'name');

        // Verify all files are included
        $runner->assertContains('test1.sql', $filenames,
            "listFiles() should include .sql files");
        $runner->assertContains('test2.gz', $filenames,
            "listFiles() should include .gz files");
        $runner->assertContains('test3.bz2', $filenames,
            "listFiles() should include .bz2 files when ext-bz2 available");
        $runner->assertContains('test4.sql.bz2', $filenames,
            "listFiles() should include .sql.bz2 files when ext-bz2 available");
        $runner->assertContains('test5.csv', $filenames,
            "listFiles() should include .csv files");

        // Verify file types are correct
        $bz2File = null;
        foreach ($files as $file) {
            if ($file['name'] === 'test3.bz2') {
                $bz2File = $file;
                break;
            }
        }

        $runner->assertEquals('BZ2', $bz2File['type'],
            "BZ2 file type should be 'BZ2'");
    } finally {
        cleanupTempConfigBz2Config($configFile);
        cleanupTempConfigDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 4: listFiles() excludes .bz2 files when ext-bz2 missing
// ----------------------------------------------------------------------------
$runner->test("listFiles() excludes .bz2 files when ext-bz2 missing", function () use ($runner, $bz2Available) {
    if ($bz2Available) {
        $runner->skip('BZ2 extension is available - cannot test exclusion behavior');
    }

    // Create temp directory with various files (using regular files since bz2 not available)
    $tempDir = sys_get_temp_dir() . '/bigdump_config_bz2_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    // Create test files
    file_put_contents($tempDir . '/test1.sql', "-- SQL\n");
    file_put_contents($tempDir . '/test2.bz2', "fake bz2 content");
    file_put_contents($tempDir . '/test3.csv', "col1,col2\n");

    $configFile = createTempConfigBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Get file list
        $files = $fileHandler->listFiles();
        $filenames = array_column($files, 'name');

        // BZ2 files should be excluded when ext-bz2 is not available
        $runner->assertNotContains('test2.bz2', $filenames,
            "listFiles() should exclude .bz2 files when ext-bz2 missing");

        // Other files should still be included
        $runner->assertContains('test1.sql', $filenames,
            "listFiles() should still include .sql files");
        $runner->assertContains('test3.csv', $filenames,
            "listFiles() should still include .csv files");
    } finally {
        cleanupTempConfigBz2Config($configFile);
        cleanupTempConfigDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 5 (Bonus): Config::isBz2Supported() static method works correctly
// ----------------------------------------------------------------------------
$runner->test("Config::isBz2Supported() returns correct value and caches result", function () use ($runner, $bz2Available) {
    // Reset cache first
    Config::resetBz2SupportCache();

    // Check that isBz2Supported returns correct value
    $result = Config::isBz2Supported();

    if ($bz2Available) {
        $runner->assertTrue($result,
            "isBz2Supported() should return true when ext-bz2 is available");
    } else {
        $runner->assertFalse($result,
            "isBz2Supported() should return false when ext-bz2 is not available");
    }

    // Call again to verify caching doesn't break anything
    $cachedResult = Config::isBz2Supported();
    $runner->assertEquals($result, $cachedResult,
        "isBz2Supported() should return same value on repeated calls (caching)");

    // Reset cache for other tests
    Config::resetBz2SupportCache();
});

// Output test results
exit($runner->summary());
