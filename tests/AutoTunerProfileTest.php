<?php

/**
 * AutoTuner Session Overhead & Profile Tests
 *
 * Tests for Task Group 4: Session Overhead Reduction & AutoTuner optimizations.
 * These tests verify:
 * - Memory usage call caching
 * - System resource cache with TTL
 * - Configurable COMMIT frequency
 * - Aggressive mode batch size multipliers
 * - Aggressive mode max_batch_size ceiling
 * - Aggressive mode safety margin reduction
 * - Effective profile exposure in metrics
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';
require_once dirname(__DIR__) . '/src/Services/FileAnalysisService.php';
require_once dirname(__DIR__) . '/src/Services/AutoTunerService.php';

use BigDump\Config\Config;
use BigDump\Services\AutoTunerService;

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
            echo "  ✓ PASS: {$name}\n";
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[$name] = $e->getMessage();
            echo "  ✗ FAIL: {$name}\n";
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

    public function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new RuntimeException(
                $message ?: "Expected value >= {$expected}, got {$actual}"
            );
        }
    }

    public function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value less than {$expected}, got {$actual}"
            );
        }
    }

    public function assertArrayHasKey(string $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new RuntimeException(
                $message ?: "Array does not have key '{$key}'"
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
// TEST SUITE: AutoTuner Session Overhead & Profile Tests
// ============================================================================

echo "AutoTuner Session Overhead & Profile Tests\n";
echo "==========================================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Memory cache reduces redundant calls
// ----------------------------------------------------------------------------
$runner->test('Memory cache reduces redundant memory_get_usage calls', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        // First call should cache the value
        $pressure1 = $autoTuner->checkMemoryPressure();
        $runner->assertArrayHasKey('usage', $pressure1);
        $runner->assertArrayHasKey('cached', $pressure1);

        // Second call should return cached value
        $pressure2 = $autoTuner->checkMemoryPressure();
        $runner->assertArrayHasKey('cached', $pressure2);

        // Usage values should be the same when cached
        $runner->assertEquals($pressure1['usage'], $pressure2['usage']);

    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: System resource cache with TTL
// ----------------------------------------------------------------------------
$runner->test('System resources cache has TTL-based invalidation', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        // First call populates cache
        $resources1 = $autoTuner->getSystemResources();
        $runner->assertArrayHasKey('cache_time', $resources1);
        $runner->assertArrayHasKey('cache_ttl', $resources1);

        // Second call uses cache
        $resources2 = $autoTuner->getSystemResources();
        $runner->assertEquals($resources1['cache_time'], $resources2['cache_time']);

        // Clear cache and verify it refreshes
        $autoTuner->clearCache();
        $resources3 = $autoTuner->getSystemResources();

        // After clear, should have new cache time
        $runner->assertArrayHasKey('cache_time', $resources3);

    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: Configurable COMMIT frequency from config
// ----------------------------------------------------------------------------
$runner->test('COMMIT frequency is configurable via config', function () use ($runner) {
    // Conservative config - frequency should be 1
    $configFile1 = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
    ]);

    try {
        $config1 = new Config($configFile1);
        $runner->assertEquals(1, $config1->get('commit_frequency'));
    } finally {
        cleanupTempConfig($configFile1);
    }

    // Aggressive config - frequency should be 3
    $configFile2 = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
    ]);

    try {
        $config2 = new Config($configFile2);
        $effectiveProfile = $config2->getEffectiveProfile();

        if ($effectiveProfile === 'aggressive') {
            $runner->assertEquals(3, $config2->get('commit_frequency'));
        } else {
            // Fallback to conservative - frequency should be 1
            $runner->assertEquals(1, $config2->get('commit_frequency'));
        }
    } finally {
        cleanupTempConfig($configFile2);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Aggressive mode batch reference multiplier (+30%)
// ----------------------------------------------------------------------------
$runner->test('Aggressive mode applies batch reference multiplier', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        if ($config->getEffectiveProfile() === 'aggressive') {
            // Aggressive mode should have higher batch size multiplier
            $metrics = $autoTuner->getMetrics();
            $runner->assertArrayHasKey('profile_multiplier', $metrics);
            $runner->assertEquals(1.3, $metrics['profile_multiplier']);
        } else {
            // Conservative mode - multiplier should be 1.0
            $metrics = $autoTuner->getMetrics();
            $runner->assertArrayHasKey('profile_multiplier', $metrics);
            $runner->assertEquals(1.0, $metrics['profile_multiplier']);
        }

    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Aggressive mode raises max_batch_size ceiling (1.5M -> 2M)
// ----------------------------------------------------------------------------
$runner->test('Aggressive mode raises max_batch_size ceiling to 2M', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        if ($config->getEffectiveProfile() === 'aggressive') {
            // Aggressive mode: max should be 2M
            $metrics = $autoTuner->getMetrics();
            $runner->assertEquals(2000000, $metrics['max_batch_size']);
        } else {
            // Conservative mode: max should be 1.5M
            $metrics = $autoTuner->getMetrics();
            $runner->assertEquals(1500000, $metrics['max_batch_size']);
        }

    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 6: Aggressive mode reduces safety margin (80% -> 70%)
// ----------------------------------------------------------------------------
$runner->test('Aggressive mode reduces safety margin to 70%', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        if ($config->getEffectiveProfile() === 'aggressive') {
            $metrics = $autoTuner->getMetrics();
            $runner->assertArrayHasKey('safety_margin', $metrics);
            $runner->assertEquals(0.7, $metrics['safety_margin']);
        } else {
            $metrics = $autoTuner->getMetrics();
            $runner->assertArrayHasKey('safety_margin', $metrics);
            $runner->assertEquals(0.8, $metrics['safety_margin']);
        }

    } finally {
        cleanupTempConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 7: Effective profile exposed in AutoTuner metrics
// ----------------------------------------------------------------------------
$runner->test('Effective profile is exposed in getMetrics()', function () use ($runner) {
    $configFile = createTempConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        $metrics = $autoTuner->getMetrics();

        // Must expose effective_profile
        $runner->assertArrayHasKey('effective_profile', $metrics);
        $runner->assertEquals('conservative', $metrics['effective_profile']);

        // Must expose profile_downgraded flag
        $runner->assertArrayHasKey('profile_downgraded', $metrics);
        $runner->assertFalse($metrics['profile_downgraded']);

    } finally {
        cleanupTempConfig($configFile);
    }
});

// Output test results
exit($runner->summary());
