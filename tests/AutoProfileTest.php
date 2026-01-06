<?php

/**
 * Auto-Aggressive Profile Tests
 *
 * Tests for the auto-aggressive mode feature (v2.25+):
 * - auto_profile_threshold config option
 * - Automatic profile upgrade for large files
 * - setTemporary() method for runtime config overrides
 * - Profile-dependent settings cascade
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Config.php';

use BigDump\Config\Config;

/**
 * Simple test runner for standalone tests
 */
class AutoProfileTestRunner
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
function createTempConfigFile(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_auto_profile_test_' . uniqid() . '.php';
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
// TEST SUITE: Auto-Aggressive Profile System
// ============================================================================

echo "Auto-Aggressive Profile Tests (v2.25+)\n";
echo "======================================\n\n";

$runner = new AutoProfileTestRunner();

// ----------------------------------------------------------------------------
// Test 1: auto_profile_threshold config option exists with default value
// ----------------------------------------------------------------------------
$runner->test('auto_profile_threshold has default value of 100MB', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        // Default should be 100MB (104857600 bytes)
        $runner->assertEquals(104857600, $config->get('auto_profile_threshold'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: auto_profile_threshold can be customized
// ----------------------------------------------------------------------------
$runner->test('auto_profile_threshold can be customized', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_profile_threshold' => 52428800, // 50MB
    ]);

    try {
        $config = new Config($configFile);

        $runner->assertEquals(52428800, $config->get('auto_profile_threshold'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: auto_profile_threshold can be disabled with 0
// ----------------------------------------------------------------------------
$runner->test('auto_profile_threshold can be disabled with 0', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_profile_threshold' => 0,
    ]);

    try {
        $config = new Config($configFile);

        $runner->assertEquals(0, $config->get('auto_profile_threshold'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 4: setTemporary() method creates runtime override
// ----------------------------------------------------------------------------
$runner->test('setTemporary() creates runtime config override', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'linespersession' => 5000,
    ]);

    try {
        $config = new Config($configFile);

        // Original value
        $runner->assertEquals(5000, $config->get('linespersession'));

        // Set temporary override
        $config->setTemporary('linespersession', 10000);

        // Should return overridden value
        $runner->assertEquals(10000, $config->get('linespersession'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: setTemporary() with performance_profile cascades profile defaults
// ----------------------------------------------------------------------------
$runner->test('setTemporary() with performance_profile cascades settings', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
    ]);

    try {
        $config = new Config($configFile);

        // Start with conservative
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
        $runner->assertEquals(5000, $config->get('linespersession')); // v2.25 conservative default

        // Check memory limit to determine expected behavior
        $memoryLimit = ini_get('memory_limit');
        $isUnlimited = $memoryLimit === '-1' || $memoryLimit === '';
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

        $minRequired = 134217728; // 128MB
        $canUseAggressive = $isUnlimited || $memLimitBytes >= $minRequired;

        // Set temporary override to aggressive
        $config->setTemporary('performance_profile', 'aggressive');

        if ($canUseAggressive) {
            // Should upgrade to aggressive with cascaded settings
            $runner->assertEquals('aggressive', $config->getEffectiveProfile());
            $runner->assertEquals(10000, $config->get('linespersession')); // v2.25 aggressive default
        } else {
            // Memory insufficient - should stay conservative
            $runner->assertEquals('conservative', $config->getEffectiveProfile());
        }
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 6: clearTemporary() removes the override
// ----------------------------------------------------------------------------
$runner->test('clearTemporary() removes the override', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'linespersession' => 5000,
    ]);

    try {
        $config = new Config($configFile);

        // Set and verify override
        $config->setTemporary('linespersession', 10000);
        $runner->assertEquals(10000, $config->get('linespersession'));

        // Clear override
        $config->clearTemporary('linespersession');

        // Should return original value
        $runner->assertEquals(5000, $config->get('linespersession'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 7: clearAllTemporary() removes all overrides
// ----------------------------------------------------------------------------
$runner->test('clearAllTemporary() removes all overrides', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'linespersession' => 5000,
        'min_batch_size' => 5000,
    ]);

    try {
        $config = new Config($configFile);

        // Set multiple overrides
        $config->setTemporary('linespersession', 10000);
        $config->setTemporary('min_batch_size', 8000);

        $runner->assertEquals(10000, $config->get('linespersession'));
        $runner->assertEquals(8000, $config->get('min_batch_size'));

        // Clear all
        $config->clearAllTemporary();

        // Should return original values
        $runner->assertEquals(5000, $config->get('linespersession'));
        $runner->assertEquals(5000, $config->get('min_batch_size'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 8: hasTemporary() checks for temporary override existence
// ----------------------------------------------------------------------------
$runner->test('hasTemporary() checks for override existence', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        $runner->assertFalse($config->hasTemporary('linespersession'));

        $config->setTemporary('linespersession', 10000);

        $runner->assertTrue($config->hasTemporary('linespersession'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 9: User-specified values are preserved during profile cascade
// ----------------------------------------------------------------------------
$runner->test('User-specified values preserved during profile cascade', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
        'linespersession' => 7500, // User override
    ]);

    try {
        $config = new Config($configFile);

        // User override should be preserved
        $runner->assertEquals(7500, $config->get('linespersession'));

        // Check if we can use aggressive
        $memoryLimit = ini_get('memory_limit');
        $isUnlimited = $memoryLimit === '-1' || $memoryLimit === '';
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

        $minRequired = 134217728; // 128MB
        $canUseAggressive = $isUnlimited || $memLimitBytes >= $minRequired;

        // Upgrade to aggressive
        $config->setTemporary('performance_profile', 'aggressive');

        if ($canUseAggressive) {
            // User-specified linespersession should still be preserved
            // (not overwritten by aggressive default of 10000)
            $runner->assertEquals(7500, $config->get('linespersession'));
        }
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 10: New batch size defaults (v2.25)
// ----------------------------------------------------------------------------
$runner->test('New batch size defaults - min_batch_size is 5000', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);

        // v2.25: min_batch_size increased from 3000 to 5000
        $runner->assertEquals(5000, $config->get('min_batch_size'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 11: Conservative linespersession is 5000 (v2.25)
// ----------------------------------------------------------------------------
$runner->test('Conservative linespersession is 5000 (v2.25)', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'conservative',
    ]);

    try {
        $config = new Config($configFile);

        // v2.25: conservative linespersession increased from 3000 to 5000
        $runner->assertEquals(5000, $config->get('linespersession'));
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 12: Aggressive linespersession is 10000 (v2.25)
// ----------------------------------------------------------------------------
$runner->test('Aggressive linespersession is 10000 (v2.25)', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'performance_profile' => 'aggressive',
    ]);

    try {
        $config = new Config($configFile);

        // Check if aggressive is available
        if ($config->getEffectiveProfile() === 'aggressive') {
            // v2.25: aggressive linespersession increased from 5000 to 10000
            $runner->assertEquals(10000, $config->get('linespersession'));
        } else {
            // Memory insufficient - profile was downgraded
            $runner->assertTrue($config->wasProfileDowngraded());
            $runner->assertEquals(5000, $config->get('linespersession')); // Conservative fallback
        }
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// Output test results
exit($runner->summary());
