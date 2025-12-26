<?php

/**
 * Integration Tests for Performance Profile System
 *
 * Tests for Task Group 5: Integration, Validation & Documentation.
 * These tests verify:
 * 1. Conservative mode end-to-end import simulation
 * 2. Aggressive mode end-to-end import simulation
 * 3. Profile switch mid-import (session resume)
 * 4. Backward compatibility with existing session data
 * 5. Memory peak tracking validation
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Load required classes
require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Services/InsertBatcherService.php';
require_once dirname(__DIR__) . '/src/Services/AutoTunerService.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';
require_once dirname(__DIR__) . '/src/Models/ImportSession.php';

use BigDump\Config\Config;
use BigDump\Services\InsertBatcherService;
use BigDump\Services\AutoTunerService;
use BigDump\Models\FileHandler;
use BigDump\Models\ImportSession;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class IntegrationTestRunner
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

    public function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual > $expected) {
            throw new RuntimeException(
                $message ?: "Expected value <= {$expected}, got {$actual}"
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
function createIntegrationConfig(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_integration_config_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupIntegrationConfig(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Creates a temporary uploads directory
 */
function createTempUploads(): string
{
    $tempDir = sys_get_temp_dir() . '/bigdump_integration_uploads_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    return $tempDir;
}

/**
 * Cleans up temporary directory
 */
function cleanupTempUploads(string $dir): void
{
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}

/**
 * Memory limit constants (bytes)
 */
const CONSERVATIVE_MEMORY_LIMIT = 67108864;  // 64MB
const AGGRESSIVE_MEMORY_LIMIT = 134217728;   // 128MB

// ============================================================================
// TEST SUITE: Integration Tests
// ============================================================================

echo "Integration Tests - Performance Profile System\n";
echo "===============================================\n\n";

$runner = new IntegrationTestRunner();

// ----------------------------------------------------------------------------
// Test 1: Conservative mode end-to-end import simulation
// ----------------------------------------------------------------------------
$runner->test('Conservative mode end-to-end import simulation', function () use ($runner) {
    $tempDir = createTempUploads();
    $configFile = createIntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'conservative',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);

        // Verify conservative profile is active
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
        $runner->assertFalse($config->wasProfileDowngraded());

        // Verify conservative defaults
        $runner->assertEquals(2000, $config->get('insert_batch_size'));
        $runner->assertEquals(16777216, $config->get('max_batch_bytes')); // 16MB
        $runner->assertEquals(65536, $config->get('file_buffer_size'));   // 64KB
        $runner->assertEquals(1, $config->get('commit_frequency'));

        // Create InsertBatcher with conservative settings
        $batcher = new InsertBatcherService(
            $config->get('insert_batch_size'),
            $config->get('max_batch_bytes')
        );

        // Simulate processing INSERT statements
        $insertCount = 0;
        $batchCount = 0;
        for ($i = 1; $i <= 100; $i++) {
            $result = $batcher->process("INSERT INTO test VALUES ({$i}, 'data{$i}');");
            $insertCount++;
            $batchCount += count($result['queries']);
        }

        // Flush remaining
        $result = $batcher->flush();
        $batchCount += count($result['queries']);

        // Verify batching worked
        $stats = $batcher->getStatistics();
        $runner->assertEquals(100, $stats['rows_batched']);
        $runner->assertGreaterThan(0, $stats['queries_executed']);

        // Verify AutoTuner uses conservative settings
        $autoTuner = new AutoTunerService($config);
        $metrics = $autoTuner->getMetrics();
        $runner->assertEquals('conservative', $metrics['effective_profile']);
        $runner->assertEquals(0.8, $metrics['safety_margin']);
        $runner->assertEquals(1500000, $metrics['max_batch_size']);
        $runner->assertEquals(1.0, $metrics['profile_multiplier']);

    } finally {
        cleanupIntegrationConfig($configFile);
        cleanupTempUploads($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 2: Aggressive mode end-to-end import simulation
// ----------------------------------------------------------------------------
$runner->test('Aggressive mode end-to-end import simulation', function () use ($runner) {
    $tempDir = createTempUploads();
    $configFile = createIntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'aggressive',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $effectiveProfile = $config->getEffectiveProfile();

        if ($effectiveProfile === 'aggressive') {
            // Aggressive mode is active - verify aggressive defaults
            $runner->assertEquals(5000, $config->get('insert_batch_size'));
            $runner->assertEquals(33554432, $config->get('max_batch_bytes')); // 32MB
            $runner->assertEquals(131072, $config->get('file_buffer_size'));  // 128KB
            $runner->assertEquals(3, $config->get('commit_frequency'));

            // Create InsertBatcher with aggressive settings
            $batcher = new InsertBatcherService(
                $config->get('insert_batch_size'),
                $config->get('max_batch_bytes')
            );

            // Simulate larger batch processing
            for ($i = 1; $i <= 200; $i++) {
                $batcher->process("INSERT INTO test VALUES ({$i}, 'data{$i}');");
            }
            $batcher->flush();

            $stats = $batcher->getStatistics();
            $runner->assertEquals(200, $stats['rows_batched']);

            // Verify AutoTuner uses aggressive settings
            $autoTuner = new AutoTunerService($config);
            $metrics = $autoTuner->getMetrics();
            $runner->assertEquals('aggressive', $metrics['effective_profile']);
            $runner->assertEquals(0.7, $metrics['safety_margin']);
            $runner->assertEquals(2000000, $metrics['max_batch_size']);
            $runner->assertEquals(1.3, $metrics['profile_multiplier']);

        } else {
            // Aggressive mode was downgraded - verify conservative fallback
            $runner->assertTrue($config->wasProfileDowngraded());
            $runner->assertEquals('conservative', $effectiveProfile);

            // Conservative defaults should be applied
            $runner->assertEquals(2000, $config->get('insert_batch_size'));
            $runner->assertEquals(16777216, $config->get('max_batch_bytes'));
        }

    } finally {
        cleanupIntegrationConfig($configFile);
        cleanupTempUploads($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 3: Profile switch mid-import (session resume simulation)
// ----------------------------------------------------------------------------
$runner->test('Profile switch mid-import (session resume)', function () use ($runner) {
    $tempDir = createTempUploads();

    // First session: Start with conservative profile
    $configFile1 = createIntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'conservative',
    ]);

    try {
        $config1 = new Config($configFile1);

        // Simulate first session progress
        $session1 = ImportSession::fromRequest(
            'test.sql',
            1,     // startLine
            0,     // startOffset
            0,     // totalQueries
            ';',   // delimiter
            '',    // pendingQuery
            false, // inString
            ''     // activeQuote
        );

        // Simulate processing some lines
        $session1->setFileSize(1000000);
        for ($i = 0; $i < 100; $i++) {
            $session1->incrementLine();
            $session1->incrementQueries();
        }
        $session1->setCurrentOffset(50000);

        // Store session state (simulating session persistence)
        $sessionData = [
            'filename' => $session1->getFilename(),
            'start_line' => $session1->getCurrentLine(),
            'offset' => $session1->getCurrentOffset(),
            'total_queries' => $session1->getTotalQueries(),
            'delimiter' => $session1->getDelimiter(),
            'pending_query' => $session1->getPendingQuery(),
            'in_string' => $session1->getInString(),
            'active_quote' => $session1->getActiveQuote(),
            'file_analysis_data' => $session1->getFileAnalysisData(),
        ];

        cleanupIntegrationConfig($configFile1);

        // Second session: Resume with aggressive profile
        $configFile2 = createIntegrationConfig([
            'db_name' => 'test',
            'db_username' => 'test',
            'upload_dir' => $tempDir,
            'performance_profile' => 'aggressive',
        ]);

        $config2 = new Config($configFile2);

        // Restore session
        $session2 = ImportSession::fromRequest(
            $sessionData['filename'],
            $sessionData['start_line'],
            $sessionData['offset'],
            $sessionData['total_queries'],
            $sessionData['delimiter'],
            $sessionData['pending_query'],
            $sessionData['in_string'],
            $sessionData['active_quote']
        );

        // Session state should be preserved
        $runner->assertEquals(101, $session2->getCurrentLine());
        $runner->assertEquals(50000, $session2->getCurrentOffset());
        $runner->assertEquals(100, $session2->getTotalQueries());

        // But config should reflect the new profile
        $profile2 = $config2->getEffectiveProfile();
        // Either aggressive (if memory allows) or conservative (if downgraded)
        $runner->assertTrue(
            in_array($profile2, ['conservative', 'aggressive'], true),
            "Profile should be valid"
        );

        // Batch size settings should change based on new profile
        if ($profile2 === 'aggressive') {
            $runner->assertEquals(5000, $config2->get('insert_batch_size'));
        } else {
            $runner->assertEquals(2000, $config2->get('insert_batch_size'));
        }

    } finally {
        cleanupIntegrationConfig($configFile2 ?? $configFile1);
        cleanupTempUploads($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Backward compatibility with existing session data (v2.18 format)
// ----------------------------------------------------------------------------
$runner->test('Backward compatibility with existing session data', function () use ($runner) {
    $tempDir = createTempUploads();

    // Simulate v2.18 config format (without new performance options)
    $configFile = createIntegrationConfig([
        'db_server' => 'localhost',
        'db_name' => 'test_db',
        'db_username' => 'user',
        'db_password' => 'pass',
        'upload_dir' => $tempDir,
        'linespersession' => 5000,
        'ajax' => true,
        'auto_tuning' => true,
        // No performance_profile, file_buffer_size, insert_batch_size, etc.
    ]);

    try {
        $config = new Config($configFile);

        // Should default to conservative profile
        $runner->assertEquals('conservative', $config->getEffectiveProfile());
        $runner->assertFalse($config->wasProfileDowngraded());

        // Should have conservative defaults applied
        $runner->assertEquals(65536, $config->get('file_buffer_size'));
        $runner->assertEquals(2000, $config->get('insert_batch_size'));
        $runner->assertEquals(16777216, $config->get('max_batch_bytes'));
        $runner->assertEquals(1, $config->get('commit_frequency'));

        // Old settings should still work
        $runner->assertEquals(5000, $config->get('linespersession'));
        $runner->assertTrue($config->get('ajax'));
        $runner->assertTrue($config->get('auto_tuning'));

        // Simulate v2.18 session data format (without new fields)
        $v218SessionData = [
            'filename' => 'old_dump.sql',
            'start_line' => 500,
            'offset' => 25000,
            'total_queries' => 450,
            'delimiter' => ';',
            'pending_query' => '',
            'in_string' => false,
            'active_quote' => '',
            // No file_analysis_data in v2.18
        ];

        // Should be able to create session from old format
        $session = ImportSession::fromRequest(
            $v218SessionData['filename'],
            $v218SessionData['start_line'],
            $v218SessionData['offset'],
            $v218SessionData['total_queries'],
            $v218SessionData['delimiter'],
            $v218SessionData['pending_query'],
            $v218SessionData['in_string'],
            $v218SessionData['active_quote']
        );

        // Session should work correctly
        $runner->assertEquals('old_dump.sql', $session->getFilename());
        $runner->assertEquals(500, $session->getCurrentLine());
        $runner->assertEquals(25000, $session->getCurrentOffset());
        $runner->assertEquals(450, $session->getTotalQueries());

        // file_analysis_data should be null (new field, not in v2.18)
        $runner->assertTrue($session->getFileAnalysisData() === null);

        // AutoTuner should work with old config
        $autoTuner = new AutoTunerService($config);
        $metrics = $autoTuner->getMetrics();
        $runner->assertEquals('conservative', $metrics['effective_profile']);

    } finally {
        cleanupIntegrationConfig($configFile);
        cleanupTempUploads($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Memory peak tracking validation
// ----------------------------------------------------------------------------
$runner->test('Memory peak tracking validation', function () use ($runner) {
    $tempDir = createTempUploads();

    // Test with conservative profile
    $configFile = createIntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'conservative',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $autoTuner = new AutoTunerService($config);

        // Track initial memory
        $initialMemory = memory_get_usage(true);

        // Simulate batch processing with memory tracking
        $batcher = new InsertBatcherService(
            $config->get('insert_batch_size'),
            $config->get('max_batch_bytes')
        );

        // Process a significant number of INSERTs
        for ($i = 1; $i <= 1000; $i++) {
            $batcher->process("INSERT INTO test VALUES ({$i}, 'data{$i}');");
        }
        $batcher->flush();

        // Check memory pressure from AutoTuner
        $pressure = $autoTuner->checkMemoryPressure();
        $runner->assertArrayHasKey('usage', $pressure);
        $runner->assertArrayHasKey('cached', $pressure);

        // Get current memory usage
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Memory metrics should be exposed in AutoTuner
        $metrics = $autoTuner->getMetrics();
        $runner->assertArrayHasKey('php_memory_usage', $metrics);
        $runner->assertArrayHasKey('memory_percentage', $metrics);

        // Memory usage should be tracked
        $runner->assertGreaterThan(0, $metrics['php_memory_usage']);

        // For conservative mode, we validate against 64MB target
        // Note: This test may not actually use 64MB, we're validating tracking works
        $effectiveProfile = $config->getEffectiveProfile();
        if ($effectiveProfile === 'conservative') {
            // Conservative safety margin is 80%
            $runner->assertEquals(0.8, $metrics['safety_margin']);
        } else {
            // Aggressive safety margin is 70%
            $runner->assertEquals(0.7, $metrics['safety_margin']);
        }

        // Memory caching should work
        $pressure2 = $autoTuner->checkMemoryPressure();
        $runner->assertTrue($pressure2['cached']);

        // Clear cache and verify refresh
        $autoTuner->clearCache();
        $pressure3 = $autoTuner->checkMemoryPressure();
        // After clear, new measurement taken (may or may not be cached depending on timing)
        $runner->assertArrayHasKey('usage', $pressure3);

    } finally {
        cleanupIntegrationConfig($configFile);
        cleanupTempUploads($tempDir);
    }
});

// Output test results
exit($runner->summary());
