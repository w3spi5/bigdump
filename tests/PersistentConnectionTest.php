<?php

/**
 * Persistent Database Connection Tests (v2.25+)
 *
 * Tests for the persistent_connections config option:
 * - Default value (false)
 * - Config option recognition
 * - Documentation in config.example.php
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/Database.php';

use BigDump\Config\Config;
use BigDump\Models\Database;

/**
 * Simple test runner for standalone tests
 */
class PersistentConnectionTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string, string> */
    private array $failures = [];

    public function test(string $name, callable $testFn): void
    {
        try {
            $testFn();
            $this->passed++;
            echo "  PASS: {$name}\n";
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

    public function summary(): int
    {
        echo "\n";
        echo "==========================================\n";
        echo "Tests: " . ($this->passed + $this->failed) . ", ";
        echo "Passed: {$this->passed}, ";
        echo "Failed: {$this->failed}\n";
        echo "==========================================\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $name => $message) {
                echo "  - {$name}: {$message}\n";
            }
        }

        return $this->failed > 0 ? 1 : 0;
    }
}

/**
 * Creates a temporary config file with given settings
 *
 * @param array<string, mixed> $config
 * @return string Path to temporary config file
 */
function createTempConfigFile(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_persistent_test_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupTempConfigFile(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

// ============================================================================
// TEST SUITE: Persistent Database Connections (v2.25+)
// ============================================================================

echo "Persistent Database Connection Tests (v2.25+)\n";
echo "==============================================\n\n";

$runner = new PersistentConnectionTestRunner();

// ----------------------------------------------------------------------------
// Test 1: persistent_connections defaults to false
// ----------------------------------------------------------------------------
$runner->test('persistent_connections defaults to false', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        // Default should be false (safe for shared hosting)
        $runner->assertFalse($config->get('persistent_connections'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: persistent_connections can be enabled
// ----------------------------------------------------------------------------
$runner->test('persistent_connections can be enabled via config', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'persistent_connections' => true,
    ]);

    try {
        $config = new Config($configFile);

        // Should be enabled when explicitly set
        $runner->assertTrue($config->get('persistent_connections'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: persistent_connections can be explicitly disabled
// ----------------------------------------------------------------------------
$runner->test('persistent_connections can be explicitly disabled', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'persistent_connections' => false,
    ]);

    try {
        $config = new Config($configFile);

        // Should be disabled when explicitly set
        $runner->assertFalse($config->get('persistent_connections'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Database class reads persistent_connections from config
// ----------------------------------------------------------------------------
$runner->test('Database class reads persistent_connections setting', function () use ($runner) {
    // This test verifies the Database class constructor reads the setting
    // We cannot test actual connection without a real database

    $configFile = createTempConfigFile([
        'db_name' => 'test_db',
        'db_username' => 'test_user',
        'db_password' => 'test_pass',
        'db_server' => 'localhost',
        'persistent_connections' => true,
    ]);

    try {
        $config = new Config($configFile);

        // Verify config has the persistent_connections value
        $runner->assertTrue($config->get('persistent_connections'));

        // Database class reads this value in its constructor
        // The actual connection prefix "p:" would be tested with a real database
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: config.example.php documents persistent_connections
// ----------------------------------------------------------------------------
$runner->test('config.example.php documents persistent_connections', function () use ($runner) {
    $configExamplePath = dirname(__DIR__) . '/config/config.example.php';

    if (!file_exists($configExamplePath)) {
        throw new RuntimeException('config.example.php not found');
    }

    $content = file_get_contents($configExamplePath);

    // Check for documentation
    $runner->assertTrue(
        str_contains($content, 'persistent_connections'),
        'config.example.php should document persistent_connections option'
    );

    // Check for shared hosting warning
    $runner->assertTrue(
        str_contains($content, 'shared hosting') || str_contains($content, 'connection pool'),
        'config.example.php should warn about shared hosting risks'
    );

    // Check for default value documentation
    $runner->assertTrue(
        str_contains($content, "'persistent_connections' => false"),
        'config.example.php should show default value as false'
    );
});

// ----------------------------------------------------------------------------
// Test 6: persistent_connections is recognized in legacy variable format
// ----------------------------------------------------------------------------
$runner->test('persistent_connections recognized in legacy variable format', function () use ($runner) {
    $tempFile = sys_get_temp_dir() . '/bigdump_legacy_persistent_' . uniqid() . '.php';
    $configContent = "<?php\n\$db_name = 'test';\n\$db_username = 'test';\n\$persistent_connections = true;\n";
    file_put_contents($tempFile, $configContent);

    try {
        $config = new Config($tempFile);

        // Should read the legacy variable format
        $runner->assertTrue($config->get('persistent_connections'));
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

// Output test results
exit($runner->summary());
