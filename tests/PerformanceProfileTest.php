<?php

/**
 * Performance Profile System Tests
 *
 * Tests for the performance profile configuration system introduced in Task Group 1.
 * These tests verify:
 * - performance_profile config option (conservative/aggressive values)
 * - Profile validation (aggressive requires 128MB+ PHP memory_limit)
 * - Fallback behavior (aggressive -> conservative when memory insufficient)
 * - Profile-based default cascading to buffer sizes and batch limits
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/src/Config/Config.php';

use BigDump\Config\Config;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class TestRunner
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

    public function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than {$expected}, got {$actual}"
            );
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
function createTempConfig(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_test_config_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupTempConfig(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

// ============================================================================
// TEST SUITE: Performance Profile System
// ============================================================================

echo "Performance Profile System Tests\n";
echo "================================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Conservative profile config option (default)
// ----------------------------------------------------------------------------
$runner->test('Conservative profile is the default', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        $runner->assertEquals('conservative', $config->get('performance_profile'));
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
        $runner->assertFalse($config->wasProfileDowngraded());
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: Aggressive profile config option
// ----------------------------------------------------------------------------
$runner->test('Aggressive profile can be explicitly set', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
    ]);

    try {
        // Note: This test may fallback to conservative if memory_limit < 128MB
        $config = new Config($configFile);

        $runner->assertEquals('aggressive', $config->get('performance_profile'));

        // Effective profile depends on system memory_limit
        $memoryLimit = ini_get('memory_limit');
        $isUnlimited = $memoryLimit === '-1' || $memoryLimit === '';

        // Parse memory limit to bytes
        $memLimitBytes = 0;
        if (!$isUnlimited && $memoryLimit !== false) {
            $unit = strtoupper(substr($memoryLimit, -1));
            $memLimitBytes = (int) $memoryLimit;
            switch ($unit) {
                case 'G':
                    $memLimitBytes *= 1024 * 1024 * 1024;
                    break;
                case 'M':
                    $memLimitBytes *= 1024 * 1024;
                    break;
                case 'K':
                    $memLimitBytes *= 1024;
                    break;
            }
        }

        // If unlimited or >= 128MB, aggressive should be active
        $minRequired = 134217728; // 128MB
        if ($isUnlimited || $memLimitBytes >= $minRequired) {
            $runner->assertEquals('aggressive', $config->getEffectiveProfile());
            $runner->assertFalse($config->wasProfileDowngraded());
        } else {
            // Fallback expected
            $runner->assertEquals('conservative', $config->getEffectiveProfile());
            $runner->assertTrue($config->wasProfileDowngraded());
        }
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: Profile validation - invalid profile value falls back to conservative
// ----------------------------------------------------------------------------
$runner->test('Invalid profile value falls back to conservative', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'invalid_value',
    ]);

    try {
        // Suppress the expected warning
        $oldLevel = error_reporting(E_ALL & ~E_USER_WARNING);
        $config = new Config($configFile);
        error_reporting($oldLevel);

        // Should fallback to conservative
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Profile-based default cascading - conservative values
// ----------------------------------------------------------------------------
$runner->test('Conservative profile applies correct default values', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
    ]);

    try {
        $config = new Config($configFile);

        // Conservative profile defaults
        $runner->assertEquals(65536, $config->get('file_buffer_size'));      // 64KB
        $runner->assertEquals(2000, $config->get('insert_batch_size'));
        $runner->assertEquals(16777216, $config->get('max_batch_bytes'));    // 16MB
        $runner->assertEquals(1, $config->get('commit_frequency'));
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Profile-based default cascading - aggressive values
// (if memory allows, otherwise tests fallback behavior)
// ----------------------------------------------------------------------------
$runner->test('Aggressive profile applies correct values or falls back correctly', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
    ]);

    try {
        $config = new Config($configFile);
        $effectiveProfile = $config->getEffectiveProfile();

        if ($effectiveProfile === 'aggressive') {
            // Aggressive profile defaults
            $runner->assertEquals(131072, $config->get('file_buffer_size'));     // 128KB
            $runner->assertEquals(5000, $config->get('insert_batch_size'));
            $runner->assertEquals(33554432, $config->get('max_batch_bytes'));    // 32MB
            $runner->assertEquals(3, $config->get('commit_frequency'));
        } else {
            // Fallback to conservative - verify downgrade happened
            $runner->assertTrue($config->wasProfileDowngraded());
            $runner->assertEquals(65536, $config->get('file_buffer_size'));
            $runner->assertEquals(2000, $config->get('insert_batch_size'));
            $runner->assertEquals(16777216, $config->get('max_batch_bytes'));
            $runner->assertEquals(1, $config->get('commit_frequency'));
        }
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 6: Buffer size validation - clamped within range
// ----------------------------------------------------------------------------
$runner->test('Buffer size is clamped within valid range (64KB - 256KB)', function () use ($runner) {
    // Test below minimum
    $configFile1 = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'file_buffer_size' => 1024, // Too small (1KB)
    ]);

    try {
        $config1 = new Config($configFile1);
        $runner->assertEquals(65536, $config1->get('file_buffer_size')); // Should clamp to 64KB
    } finally {
        cleanupTempConfig($configFile1);
    }

    // Test above maximum
    $configFile2 = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'file_buffer_size' => 524288, // Too large (512KB)
    ]);

    try {
        $config2 = new Config($configFile2);
        $runner->assertEquals(262144, $config2->get('file_buffer_size')); // Should clamp to 256KB
    } finally {
        cleanupTempConfig($configFile2);
    }

    // Test within range
    $configFile3 = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'file_buffer_size' => 131072, // Valid (128KB)
    ]);

    try {
        $config3 = new Config($configFile3);
        $runner->assertEquals(131072, $config3->get('file_buffer_size')); // Should keep as-is
    } finally {
        cleanupTempConfig($configFile3);
    }
});

// ----------------------------------------------------------------------------
// Test 7: getEffectiveProfile() method returns correct value
// ----------------------------------------------------------------------------
$runner->test('getEffectiveProfile() returns validated profile', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $effectiveProfile = $config->getEffectiveProfile();

        // Must be one of the valid profiles
        $runner->assertTrue(
            in_array($effectiveProfile, ['conservative', 'aggressive'], true),
            "Effective profile must be 'conservative' or 'aggressive'"
        );
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 8: getProfileInfo() returns comprehensive debugging information
// ----------------------------------------------------------------------------
$runner->test('getProfileInfo() returns complete profile information', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
    ]);

    try {
        $config = new Config($configFile);
        $profileInfo = $config->getProfileInfo();

        // Verify all expected keys exist
        $runner->assertTrue(isset($profileInfo['requested_profile']));
        $runner->assertTrue(isset($profileInfo['effective_profile']));
        $runner->assertTrue(isset($profileInfo['was_downgraded']));
        $runner->assertTrue(isset($profileInfo['memory_limit_bytes']));
        $runner->assertTrue(isset($profileInfo['aggressive_min_memory']));
        $runner->assertTrue(isset($profileInfo['profile_settings']));

        // Verify profile settings structure
        $settings = $profileInfo['profile_settings'];
        $runner->assertTrue(isset($settings['file_buffer_size']));
        $runner->assertTrue(isset($settings['insert_batch_size']));
        $runner->assertTrue(isset($settings['max_batch_bytes']));
        $runner->assertTrue(isset($settings['commit_frequency']));

        // Verify values match config
        $runner->assertEquals($config->get('file_buffer_size'), $settings['file_buffer_size']);
        $runner->assertEquals($config->get('insert_batch_size'), $settings['insert_batch_size']);
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 9: Backward compatibility - existing configs work without modification
// ----------------------------------------------------------------------------
$runner->test('Existing configs without performance_profile work correctly', function () use ($runner) {
    // Simulate a v2.18 config without new options
    $configFile = createTempConfig([
        'db_server' => 'localhost',
        'db_name' => 'test_db',
        'db_username' => 'user',
        'db_password' => 'pass',
        'linespersession' => 5000,
        'ajax' => true,
    ]);

    try {
        $config = new Config($configFile);

        // Should work and default to conservative
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
        $runner->assertFalse($config->wasProfileDowngraded());

        // Old options should still work
        $runner->assertEquals('localhost', $config->get('db_server'));
        $runner->assertEquals('test_db', $config->get('db_name'));
        $runner->assertEquals(5000, $config->get('linespersession'));
        $runner->assertTrue($config->get('ajax'));
    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 10: User overrides take precedence over profile defaults
// ----------------------------------------------------------------------------
$runner->test('User-specified values override profile defaults', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
        'insert_batch_size' => 3000, // Override conservative default of 2000
        'file_buffer_size' => 98304, // Override conservative default of 65536 (96KB)
    ]);

    try {
        $config = new Config($configFile);

        // User overrides should be preserved
        $runner->assertEquals(3000, $config->get('insert_batch_size'));
        $runner->assertEquals(98304, $config->get('file_buffer_size'));

        // Non-overridden values should use profile defaults
        $runner->assertEquals(16777216, $config->get('max_batch_bytes'));
        $runner->assertEquals(1, $config->get('commit_frequency'));
    } finally {
        cleanupTempConfig($configFile);
    }
});

// Output test results
exit($runner->summary());
