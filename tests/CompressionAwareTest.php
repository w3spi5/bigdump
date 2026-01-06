<?php

/**
 * Compression-Aware Session Sizing Tests (v2.25+)
 *
 * Tests for compression-aware batch sizing:
 * - FileHandler.getCompressionType() method
 * - AutoTunerService compression multipliers
 * - Compression-aware batch size calculation
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';
require_once dirname(__DIR__) . '/src/Services/FileAnalysisService.php'; // FileAnalysisResult is defined here
require_once dirname(__DIR__) . '/src/Services/AutoTunerService.php';

use BigDump\Config\Config;
use BigDump\Models\FileHandler;
use BigDump\Services\AutoTunerService;
use BigDump\Services\FileAnalysisResult;

/**
 * Simple test runner for standalone tests
 */
class CompressionAwareTestRunner
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

    public function assertGreaterThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than {$expected}, got {$actual}"
            );
        }
    }

    public function assertLessThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value less than {$expected}, got {$actual}"
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
    $tempFile = sys_get_temp_dir() . '/bigdump_compression_test_' . uniqid() . '.php';
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
// TEST SUITE: Compression-Aware Session Sizing (v2.25+)
// ============================================================================

echo "Compression-Aware Session Sizing Tests (v2.25+)\n";
echo "================================================\n\n";

$runner = new CompressionAwareTestRunner();

// ============================================================================
// PART 1: FileHandler.getCompressionType() Tests
// ============================================================================

echo "--- FileHandler.getCompressionType() ---\n\n";

// ----------------------------------------------------------------------------
// Test 1: Plain SQL file returns COMPRESSION_NONE
// ----------------------------------------------------------------------------
$runner->test('Plain SQL file returns COMPRESSION_NONE', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $result = $fileHandler->getCompressionType('database.sql');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $result);

        $result2 = $fileHandler->getCompressionType('dump_20240101.sql');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $result2);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: GZIP file returns COMPRESSION_GZIP
// ----------------------------------------------------------------------------
$runner->test('GZIP file returns COMPRESSION_GZIP', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $result = $fileHandler->getCompressionType('database.sql.gz');
        $runner->assertEquals(FileHandler::COMPRESSION_GZIP, $result);

        $result2 = $fileHandler->getCompressionType('dump.gz');
        $runner->assertEquals(FileHandler::COMPRESSION_GZIP, $result2);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: BZ2 file returns COMPRESSION_BZ2
// ----------------------------------------------------------------------------
$runner->test('BZ2 file returns COMPRESSION_BZ2', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $result = $fileHandler->getCompressionType('database.sql.bz2');
        $runner->assertEquals(FileHandler::COMPRESSION_BZ2, $result);

        $result2 = $fileHandler->getCompressionType('dump.bz2');
        $runner->assertEquals(FileHandler::COMPRESSION_BZ2, $result2);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 4: CSV file returns COMPRESSION_NONE
// ----------------------------------------------------------------------------
$runner->test('CSV file returns COMPRESSION_NONE', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $result = $fileHandler->getCompressionType('data.csv');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $result);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Unknown extension returns COMPRESSION_NONE
// ----------------------------------------------------------------------------
$runner->test('Unknown extension returns COMPRESSION_NONE', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $result = $fileHandler->getCompressionType('file.txt');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $result);

        $result2 = $fileHandler->getCompressionType('nodot');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $result2);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ============================================================================
// PART 2: AutoTunerService Compression Multipliers
// ============================================================================

echo "\n--- AutoTunerService Compression Multipliers ---\n\n";

// ----------------------------------------------------------------------------
// Test 6: Compression multipliers are correctly defined
// ----------------------------------------------------------------------------
$runner->test('Compression multipliers have correct values', function () use ($runner) {
    $multipliers = AutoTunerService::getCompressionMultipliers();

    // Plain SQL: 1.5x (larger batches OK)
    $runner->assertEquals(1.5, $multipliers[FileHandler::COMPRESSION_NONE]);

    // GZIP: 1.0x (baseline)
    $runner->assertEquals(1.0, $multipliers[FileHandler::COMPRESSION_GZIP]);

    // BZ2: 0.7x (smaller batches due to memory overhead)
    $runner->assertEquals(0.7, $multipliers[FileHandler::COMPRESSION_BZ2]);
});

// ----------------------------------------------------------------------------
// Test 7: AutoTunerService setCompressionType works
// ----------------------------------------------------------------------------
$runner->test('AutoTunerService setCompressionType works', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $tuner = new AutoTunerService($config);

        // Default is NONE
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $tuner->getCompressionType());

        // Set to GZIP
        $tuner->setCompressionType(FileHandler::COMPRESSION_GZIP);
        $runner->assertEquals(FileHandler::COMPRESSION_GZIP, $tuner->getCompressionType());

        // Set to BZ2
        $tuner->setCompressionType(FileHandler::COMPRESSION_BZ2);
        $runner->assertEquals(FileHandler::COMPRESSION_BZ2, $tuner->getCompressionType());
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 8: getCompressionMultiplier returns correct value
// ----------------------------------------------------------------------------
$runner->test('getCompressionMultiplier returns correct value for type', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $tuner = new AutoTunerService($config);

        // Default (NONE) should be 1.5
        $runner->assertEquals(1.5, $tuner->getCompressionMultiplier());

        // Set to GZIP, should be 1.0
        $tuner->setCompressionType(FileHandler::COMPRESSION_GZIP);
        $runner->assertEquals(1.0, $tuner->getCompressionMultiplier());

        // Set to BZ2, should be 0.7
        $tuner->setCompressionType(FileHandler::COMPRESSION_BZ2);
        $runner->assertEquals(0.7, $tuner->getCompressionMultiplier());
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 9: setCompressionTypeFromFile works with FileHandler
// ----------------------------------------------------------------------------
$runner->test('setCompressionTypeFromFile detects compression correctly', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);
        $tuner = new AutoTunerService($config);

        // Test SQL file
        $tuner->setCompressionTypeFromFile($fileHandler, 'dump.sql');
        $runner->assertEquals(FileHandler::COMPRESSION_NONE, $tuner->getCompressionType());

        // Test GZ file
        $tuner->setCompressionTypeFromFile($fileHandler, 'dump.sql.gz');
        $runner->assertEquals(FileHandler::COMPRESSION_GZIP, $tuner->getCompressionType());

        // Test BZ2 file
        $tuner->setCompressionTypeFromFile($fileHandler, 'dump.sql.bz2');
        $runner->assertEquals(FileHandler::COMPRESSION_BZ2, $tuner->getCompressionType());
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ============================================================================
// PART 3: Batch Size Calculation with Compression
// ============================================================================

echo "\n--- Compression-Aware Batch Sizing ---\n\n";

// ----------------------------------------------------------------------------
// Test 10: Plain SQL gets larger batch size than BZ2
// ----------------------------------------------------------------------------
$runner->test('Plain SQL gets larger batch size than BZ2', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);

        // Create two tuners
        $tunerSql = new AutoTunerService($config);
        $tunerBz2 = new AutoTunerService($config);

        // Set compression types
        $tunerSql->setCompressionType(FileHandler::COMPRESSION_NONE); // 1.5x
        $tunerBz2->setCompressionType(FileHandler::COMPRESSION_BZ2);  // 0.7x

        // Calculate batch sizes
        $batchSql = $tunerSql->calculateOptimalBatchSize();
        $batchBz2 = $tunerBz2->calculateOptimalBatchSize();

        // Plain SQL should have larger batch than BZ2
        // Ratio should be approximately 1.5/0.7 = ~2.14x
        $runner->assertGreaterThan($batchBz2, $batchSql);

        // The ratio should be close to expected (within 50% tolerance due to clamping)
        $ratio = $batchSql / $batchBz2;
        $runner->assertGreaterThan(1.5, $ratio); // At least 1.5x difference
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 11: GZIP gets medium batch size (between SQL and BZ2)
// ----------------------------------------------------------------------------
$runner->test('GZIP gets batch size between plain SQL and BZ2', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);

        $tunerSql = new AutoTunerService($config);
        $tunerGzip = new AutoTunerService($config);
        $tunerBz2 = new AutoTunerService($config);

        $tunerSql->setCompressionType(FileHandler::COMPRESSION_NONE);
        $tunerGzip->setCompressionType(FileHandler::COMPRESSION_GZIP);
        $tunerBz2->setCompressionType(FileHandler::COMPRESSION_BZ2);

        $batchSql = $tunerSql->calculateOptimalBatchSize();
        $batchGzip = $tunerGzip->calculateOptimalBatchSize();
        $batchBz2 = $tunerBz2->calculateOptimalBatchSize();

        // GZIP should be <= SQL (1.0x vs 1.5x)
        $runner->assertTrue(
            $batchGzip <= $batchSql,
            "GZIP batch ({$batchGzip}) should be <= SQL batch ({$batchSql})"
        );

        // GZIP should be >= BZ2 (1.0x vs 0.7x)
        $runner->assertTrue(
            $batchGzip >= $batchBz2,
            "GZIP batch ({$batchGzip}) should be >= BZ2 batch ({$batchBz2})"
        );
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 12: Metrics include compression type and multiplier
// ----------------------------------------------------------------------------
$runner->test('Metrics include compression type and multiplier', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $tuner = new AutoTunerService($config);

        $tuner->setCompressionType(FileHandler::COMPRESSION_BZ2);
        $metrics = $tuner->getMetrics();

        // Should have compression_type
        $runner->assertTrue(
            array_key_exists('compression_type', $metrics),
            'Metrics should include compression_type'
        );
        $runner->assertEquals(FileHandler::COMPRESSION_BZ2, $metrics['compression_type']);

        // Should have compression_multiplier
        $runner->assertTrue(
            array_key_exists('compression_multiplier', $metrics),
            'Metrics should include compression_multiplier'
        );
        $runner->assertEquals(0.7, $metrics['compression_multiplier']);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 13: adaptBatchSize considers compression type
// ----------------------------------------------------------------------------
$runner->test('adaptBatchSize considers compression multiplier', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
    ]);

    try {
        $config = new Config($configFile);
        $tuner = new AutoTunerService($config);

        // Set BZ2 compression (0.7x multiplier)
        $tuner->setCompressionType(FileHandler::COMPRESSION_BZ2);

        // Simulate low memory usage to trigger increase
        // Need at least 3 samples
        $result1 = $tuner->adaptBatchSize(100000, 20);
        $result2 = $tuner->adaptBatchSize(100000, 20);
        $result3 = $tuner->adaptBatchSize(100000, 20);

        // After collecting samples, should potentially increase
        // The key is that compression_multiplier is tracked
        $runner->assertTrue(
            array_key_exists('compression_multiplier', $result3),
            'adaptBatchSize result should include compression_multiplier'
        );
        $runner->assertEquals(0.7, $result3['compression_multiplier']);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 14: BZ2 with large file analysis gets conservative batch
// ----------------------------------------------------------------------------
$runner->test('BZ2 with large file gets conservative batch sizing', function () use ($runner) {
    $configFile = createTempConfigFile([
        'db_name' => 'test',
        'db_username' => 'test',
        'auto_tuning' => true,
        'file_aware_tuning' => true,
    ]);

    try {
        $config = new Config($configFile);
        $tuner = new AutoTunerService($config);

        // Create a "large" file analysis result
        $analysis = new FileAnalysisResult(
            fileSize: 200 * 1024 * 1024, // 200MB
            category: 'large',
            categoryLabel: 'Large (100MB - 500MB)',
            estimatedLines: 2000000,
            avgBytesPerLine: 100.0,
            isBulkInsert: true,
            targetRamUsage: 0.60,
            isGzip: false,
            isEstimate: false
        );

        $tuner->setFileAnalysis($analysis);
        $tuner->setCompressionType(FileHandler::COMPRESSION_BZ2);

        $batchBz2 = $tuner->calculateOptimalBatchSize();

        // Now compare with plain SQL
        $tuner2 = new AutoTunerService($config);
        $tuner2->setFileAnalysis($analysis);
        $tuner2->setCompressionType(FileHandler::COMPRESSION_NONE);

        $batchSql = $tuner2->calculateOptimalBatchSize();

        // BZ2 should be smaller (more conservative)
        $runner->assertLessThan($batchSql, $batchBz2);

        // Both should be reasonable values
        $runner->assertGreaterThan(1000, $batchBz2);
        $runner->assertGreaterThan(1000, $batchSql);
    } finally {
        cleanupTempConfigFile($configFile);
    }
});

// Output test results
exit($runner->summary());
