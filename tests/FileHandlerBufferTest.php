<?php

/**
 * File I/O Buffer Optimization Tests
 *
 * Tests for the FileHandler buffer configuration system introduced in Task Group 3.
 * These tests verify:
 * - Configurable buffer size via `file_buffer_size` config
 * - Buffer size range validation (64KB - 256KB)
 * - Buffer auto-adjustment based on file category
 * - Gzip mode respects same buffer size
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';
require_once dirname(__DIR__) . '/src/Services/FileAnalysisService.php';

use BigDump\Config\Config;
use BigDump\Models\FileHandler;
use BigDump\Services\FileAnalysisResult;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class BufferTestRunner
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

    public function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new RuntimeException(
                $message ?: "Expected value >= {$expected}, got {$actual}"
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
function createTempBufferConfig(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_buffer_test_config_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupTempBufferConfig(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Creates a temporary SQL test file with sample content
 *
 * @param string $content Content to write
 * @param string $extension File extension (sql or gz)
 * @return string Path to temporary file
 */
function createTempSqlFile(string $content, string $extension = 'sql'): string
{
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $filename = 'test_file_' . uniqid() . '.' . $extension;
    $filepath = $tempDir . '/' . $filename;

    if ($extension === 'gz') {
        $gz = gzopen($filepath, 'wb9');
        gzwrite($gz, $content);
        gzclose($gz);
    } else {
        file_put_contents($filepath, $content);
    }

    return $filepath;
}

/**
 * Cleans up temporary test directory
 */
function cleanupTempDir(string $filepath): void
{
    $dir = dirname($filepath);
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    if (is_dir($dir) && strpos($dir, 'bigdump_test_uploads') !== false) {
        @rmdir($dir);
    }
}

// ============================================================================
// TEST SUITE: File I/O Buffer Optimization
// ============================================================================

echo "File I/O Buffer Optimization Tests\n";
echo "===================================\n\n";

$runner = new BufferTestRunner();

// Buffer size constants for validation
$MIN_BUFFER = 65536;   // 64KB
$MAX_BUFFER = 262144;  // 256KB

// ----------------------------------------------------------------------------
// Test 1: Configurable buffer size via file_buffer_size config
// ----------------------------------------------------------------------------
$runner->test('Buffer size is configurable via file_buffer_size config', function () use ($runner, $MIN_BUFFER) {
    // Test with custom buffer size
    $customBufferSize = 131072; // 128KB
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    $configFile = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'file_buffer_size' => $customBufferSize,
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $runner->assertEquals($customBufferSize, $fileHandler->getBufferSize(),
            "Buffer size should match configured value");
    } finally {
        cleanupTempBufferConfig($configFile);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 2: Buffer size range validation (64KB - 256KB)
// ----------------------------------------------------------------------------
$runner->test('Buffer size is clamped within valid range (64KB - 256KB)', function () use ($runner, $MIN_BUFFER, $MAX_BUFFER) {
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    // Test below minimum (should clamp to 64KB)
    $configFile1 = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'file_buffer_size' => 1024, // 1KB - too small
    ]);

    try {
        $config1 = new Config($configFile1);
        $fileHandler1 = new FileHandler($config1);

        $runner->assertEquals($MIN_BUFFER, $fileHandler1->getBufferSize(),
            "Buffer size below minimum should clamp to 64KB");
    } finally {
        cleanupTempBufferConfig($configFile1);
    }

    // Test above maximum (should clamp to 256KB)
    $configFile2 = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'file_buffer_size' => 524288, // 512KB - too large
    ]);

    try {
        $config2 = new Config($configFile2);
        $fileHandler2 = new FileHandler($config2);

        $runner->assertEquals($MAX_BUFFER, $fileHandler2->getBufferSize(),
            "Buffer size above maximum should clamp to 256KB");
    } finally {
        cleanupTempBufferConfig($configFile2);
    }

    // Test within range (should keep as-is)
    $configFile3 = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'file_buffer_size' => 98304, // 96KB - valid
    ]);

    try {
        $config3 = new Config($configFile3);
        $fileHandler3 = new FileHandler($config3);

        $runner->assertEquals(98304, $fileHandler3->getBufferSize(),
            "Buffer size within range should be kept as-is");
    } finally {
        cleanupTempBufferConfig($configFile3);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 3: Buffer auto-adjustment based on file category
// ----------------------------------------------------------------------------
$runner->test('Buffer size auto-adjusts based on file category', function () use ($runner, $MIN_BUFFER, $MAX_BUFFER) {
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    // Test with aggressive profile to allow larger buffers
    $configFile = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'aggressive',
        'file_buffer_size' => 131072, // 128KB
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Test tiny category - should use smaller buffer
        $fileHandler->setBufferSizeForCategory('tiny');
        $tinyBuffer = $fileHandler->getBufferSize();
        $runner->assertEquals($MIN_BUFFER, $tinyBuffer,
            "Tiny files should use minimum buffer (64KB)");

        // Test small category - should use smaller buffer
        $fileHandler->setBufferSizeForCategory('small');
        $smallBuffer = $fileHandler->getBufferSize();
        $runner->assertEquals($MIN_BUFFER, $smallBuffer,
            "Small files should use minimum buffer (64KB)");

        // Test large category - should use larger buffer (aggressive mode)
        $fileHandler->setBufferSizeForCategory('large');
        $largeBuffer = $fileHandler->getBufferSize();
        $runner->assertGreaterThanOrEqual(131072, $largeBuffer,
            "Large files in aggressive mode should use >= 128KB buffer");

        // Test massive category - should use largest buffer (aggressive mode)
        $fileHandler->setBufferSizeForCategory('massive');
        $massiveBuffer = $fileHandler->getBufferSize();
        $runner->assertEquals($MAX_BUFFER, $massiveBuffer,
            "Massive files in aggressive mode should use maximum buffer (256KB)");
    } finally {
        cleanupTempBufferConfig($configFile);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Conservative profile respects category buffer limits
// ----------------------------------------------------------------------------
$runner->test('Conservative profile respects buffer limits per category', function () use ($runner, $MIN_BUFFER) {
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    // Test with conservative profile
    $configFile = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'conservative',
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Get the initial buffer size (should be 64KB for conservative)
        $initialBuffer = $fileHandler->getBufferSize();
        $runner->assertEquals($MIN_BUFFER, $initialBuffer,
            "Conservative profile should use 64KB buffer by default");

        // In conservative mode, large category should be limited by profile buffer
        $fileHandler->setBufferSizeForCategory('large');
        $largeBuffer = $fileHandler->getBufferSize();
        $runner->assertEquals($MIN_BUFFER, $largeBuffer,
            "Large files in conservative mode should be limited to profile buffer size");

        // Test with a medium file - should still respect conservative limits
        $fileHandler->setBufferSizeForCategory('medium');
        $mediumBuffer = $fileHandler->getBufferSize();
        $runner->assertLessThanOrEqual($MIN_BUFFER, $mediumBuffer,
            "Medium files in conservative mode should use profile buffer size or less");
    } finally {
        cleanupTempBufferConfig($configFile);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Gzip mode uses same configurable buffer size
// ----------------------------------------------------------------------------
$runner->test('Gzip mode uses same configurable buffer size', function () use ($runner) {
    // Create test content
    $sqlContent = "-- Test SQL file\n";
    $sqlContent .= "INSERT INTO test VALUES (1, 'test');\n";
    $sqlContent .= str_repeat("INSERT INTO test VALUES (2, 'more data');\n", 100);

    // Create temporary gzip file
    $gzFilepath = createTempSqlFile($sqlContent, 'gz');
    $tempDir = dirname($gzFilepath);

    $customBufferSize = 131072; // 128KB
    $configFile = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'file_buffer_size' => $customBufferSize,
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Verify buffer size is set correctly before opening
        $runner->assertEquals($customBufferSize, $fileHandler->getBufferSize(),
            "Buffer size should be configured before opening file");

        // Open the gzip file
        $filename = basename($gzFilepath);
        $fileHandler->open($filename);

        // Verify gzip mode is active
        $runner->assertTrue($fileHandler->isGzipMode(),
            "File should be opened in gzip mode");

        // Verify buffer size is still the configured value
        $runner->assertEquals($customBufferSize, $fileHandler->getBufferSize(),
            "Gzip mode should use the same configured buffer size");

        // Read some lines to ensure it works
        $lineCount = 0;
        while (($line = $fileHandler->readLine()) !== false) {
            $lineCount++;
            if ($lineCount > 10) break;
        }
        $runner->assertGreaterThanOrEqual(1, $lineCount,
            "Should be able to read lines from gzip file");

        $fileHandler->close();
    } finally {
        cleanupTempBufferConfig($configFile);
        cleanupTempDir($gzFilepath);
    }
});

// ----------------------------------------------------------------------------
// Test 6: Buffer size from FileAnalysisResult (integration test)
// ----------------------------------------------------------------------------
$runner->test('Buffer size can be set from FileAnalysisResult', function () use ($runner, $MAX_BUFFER) {
    $tempDir = sys_get_temp_dir() . '/bigdump_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    $configFile = createTempBufferConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'performance_profile' => 'aggressive',
        'file_buffer_size' => 262144, // Max 256KB
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Create a mock FileAnalysisResult for a massive file
        $analysisResult = new FileAnalysisResult(
            fileSize: 3000000000, // 3GB
            category: 'massive',
            categoryLabel: 'Massive (2GB+)',
            estimatedLines: 15000000,
            avgBytesPerLine: 200.0,
            isBulkInsert: false,
            targetRamUsage: 0.75,
            isGzip: false,
            isEstimate: false
        );

        // Set buffer size from analysis result
        $fileHandler->setBufferSizeFromAnalysis($analysisResult);

        // Verify buffer is set to maximum for massive files in aggressive mode
        $runner->assertEquals($MAX_BUFFER, $fileHandler->getBufferSize(),
            "Massive file in aggressive mode should use maximum buffer");
    } finally {
        cleanupTempBufferConfig($configFile);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 7: Static helper methods work correctly
// ----------------------------------------------------------------------------
$runner->test('Static helper methods return correct values', function () use ($runner, $MIN_BUFFER, $MAX_BUFFER) {
    // Test getBufferSizeConstraints()
    $constraints = FileHandler::getBufferSizeConstraints();
    $runner->assertEquals($MIN_BUFFER, $constraints['min'],
        "Min buffer constraint should be 64KB");
    $runner->assertEquals($MAX_BUFFER, $constraints['max'],
        "Max buffer constraint should be 256KB");

    // Test getCategoryBufferSizes()
    $categoryBuffers = FileHandler::getCategoryBufferSizes();
    $runner->assertTrue(isset($categoryBuffers['tiny']),
        "Category buffer sizes should include 'tiny'");
    $runner->assertTrue(isset($categoryBuffers['massive']),
        "Category buffer sizes should include 'massive'");
    $runner->assertEquals($MIN_BUFFER, $categoryBuffers['tiny'],
        "Tiny category should recommend minimum buffer");
    $runner->assertEquals($MAX_BUFFER, $categoryBuffers['massive'],
        "Massive category should recommend maximum buffer");
});

// Output test results
exit($runner->summary());
