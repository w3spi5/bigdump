<?php

/**
 * FileHandler BZ2 Compression Support Tests
 *
 * Tests for the FileHandler BZ2 mode introduced for .bz2 file support.
 * These tests verify:
 * - BZ2 extension detection (.bz2, .sql.bz2, case-insensitive)
 * - bzopen/bzread/bzclose operations
 * - Seek workaround (re-read from start)
 * - Graceful fallback when ext-bz2 missing
 * - EOF detection in bz2 mode
 * - Mixed mode operations (ensure gzip still works)
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
class Bz2TestRunner
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
        } catch (SkipTestException $e) {
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

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new RuntimeException(
                $message ?: "Expected string to contain '{$needle}', got '{$haystack}'"
            );
        }
    }

    public function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value > {$expected}, got {$actual}"
            );
        }
    }

    public function skip(string $reason): void
    {
        throw new SkipTestException($reason);
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
 * Exception for skipping tests (e.g., when ext-bz2 is not available)
 */
class SkipTestException extends Exception {}

/**
 * Creates a temporary config file with given settings
 *
 * @param array<string, mixed> $config
 * @return string Path to temporary config file
 */
function createTempBz2Config(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_bz2_test_config_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupTempBz2Config(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Creates a temporary SQL file with sample content, optionally compressed
 *
 * @param string $content Content to write
 * @param string $extension File extension (sql, gz, or bz2)
 * @return string Path to temporary file
 */
function createTempBz2SqlFile(string $content, string $extension = 'sql'): string
{
    $tempDir = sys_get_temp_dir() . '/bigdump_bz2_test_uploads_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $filename = 'test_file_' . uniqid() . '.' . $extension;
    $filepath = $tempDir . '/' . $filename;

    if ($extension === 'gz') {
        $gz = gzopen($filepath, 'wb9');
        gzwrite($gz, $content);
        gzclose($gz);
    } elseif ($extension === 'bz2') {
        if (!function_exists('bzopen')) {
            throw new RuntimeException('BZ2 extension not available for creating test file');
        }
        $bz = bzopen($filepath, 'w');
        bzwrite($bz, $content);
        bzclose($bz);
    } else {
        file_put_contents($filepath, $content);
    }

    return $filepath;
}

/**
 * Cleans up temporary test directory
 */
function cleanupTempBz2Dir(string $filepath): void
{
    $dir = dirname($filepath);
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    if (is_dir($dir) && strpos($dir, 'bigdump_bz2_test_uploads') !== false) {
        // Remove any other files in the directory
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
// TEST SUITE: FileHandler BZ2 Compression Support
// ============================================================================

echo "FileHandler BZ2 Compression Support Tests\n";
echo "==========================================\n\n";

$runner = new Bz2TestRunner();

// Check if BZ2 extension is available
$bz2Available = function_exists('bzopen');
echo "BZ2 Extension Available: " . ($bz2Available ? "YES" : "NO") . "\n\n";

// ----------------------------------------------------------------------------
// Test 1: BZ2 extension detection (.bz2, .sql.bz2, case-insensitive)
// ----------------------------------------------------------------------------
$runner->test('BZ2 extension detection works correctly', function () use ($runner, $bz2Available) {
    $tempDir = sys_get_temp_dir() . '/bigdump_bz2_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Test extension detection for various cases
        $runner->assertEquals('bz2', $fileHandler->getExtension('test.bz2'),
            "Should detect .bz2 extension");
        $runner->assertEquals('bz2', $fileHandler->getExtension('test.sql.bz2'),
            "Should detect .sql.bz2 extension (returns bz2)");
        $runner->assertEquals('bz2', $fileHandler->getExtension('TEST.BZ2'),
            "Should detect .BZ2 extension (case-insensitive)");
        $runner->assertEquals('bz2', $fileHandler->getExtension('dump.Bz2'),
            "Should detect .Bz2 extension (mixed case)");
    } finally {
        cleanupTempBz2Config($configFile);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 2: BZ2 open/read/close operations
// ----------------------------------------------------------------------------
$runner->test('BZ2 open/read/close operations work correctly', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    // Create test content
    $sqlContent = "-- Test BZ2 SQL file\n";
    $sqlContent .= "CREATE TABLE bz2test (id INT PRIMARY KEY);\n";
    $sqlContent .= "INSERT INTO bz2test VALUES (1);\n";
    $sqlContent .= "INSERT INTO bz2test VALUES (2);\n";
    $sqlContent .= "INSERT INTO bz2test VALUES (3);\n";

    // Create temporary bz2 file
    $bz2Filepath = createTempBz2SqlFile($sqlContent, 'bz2');
    $tempDir = dirname($bz2Filepath);

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Open the bz2 file
        $filename = basename($bz2Filepath);
        $result = $fileHandler->open($filename);
        $runner->assertTrue($result, "Should successfully open BZ2 file");

        // Verify bz2 mode is active
        $runner->assertTrue($fileHandler->isBz2Mode(), "Should be in BZ2 mode");
        $runner->assertFalse($fileHandler->isGzipMode(), "Should not be in GZip mode");

        // Read lines and verify content
        $lines = [];
        while (($line = $fileHandler->readLine()) !== false) {
            $lines[] = $line;
        }

        $runner->assertEquals(5, count($lines), "Should read 5 lines from BZ2 file");
        $runner->assertStringContains('CREATE TABLE', $lines[1], "Second line should contain CREATE TABLE");
        $runner->assertStringContains('INSERT INTO', $lines[2], "Third line should contain INSERT INTO");

        // Close the file
        $fileHandler->close();

        // Verify mode is reset after close
        $runner->assertFalse($fileHandler->isBz2Mode(), "BZ2 mode should be reset after close");
    } finally {
        cleanupTempBz2Config($configFile);
        cleanupTempBz2Dir($bz2Filepath);
    }
});

// ----------------------------------------------------------------------------
// Test 3: BZ2 seek workaround (re-read from start)
// ----------------------------------------------------------------------------
$runner->test('BZ2 seek workaround re-reads from start to target position', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    // Create test content with known byte positions
    $line1 = "LINE 1: First line of content\n";     // 30 bytes
    $line2 = "LINE 2: Second line of content\n";    // 31 bytes
    $line3 = "LINE 3: Third line of content\n";     // 30 bytes
    $line4 = "LINE 4: Fourth line of content\n";    // 31 bytes
    $line5 = "LINE 5: Fifth line of content\n";     // 30 bytes

    $sqlContent = $line1 . $line2 . $line3 . $line4 . $line5;

    // Create temporary bz2 file
    $bz2Filepath = createTempBz2SqlFile($sqlContent, 'bz2');
    $tempDir = dirname($bz2Filepath);

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $filename = basename($bz2Filepath);
        $fileHandler->open($filename);

        // Read first two lines
        $fileHandler->readLine(); // LINE 1
        $fileHandler->readLine(); // LINE 2

        // Get current position (should be after line 2)
        $posAfterLine2 = $fileHandler->tell();
        $runner->assertGreaterThan(0, $posAfterLine2, "Position should be > 0 after reading lines");

        // Seek to start of line 3 (after line 1 and line 2 = 61 bytes)
        $targetPosition = strlen($line1) + strlen($line2); // 61
        $seekResult = $fileHandler->seek($targetPosition);
        $runner->assertTrue($seekResult, "Seek should succeed");

        // Read line 3
        $line3Read = $fileHandler->readLine();
        $runner->assertStringContains('LINE 3', $line3Read, "Should read LINE 3 after seek");

        // Verify position after reading
        $posAfterSeekRead = $fileHandler->tell();
        $expectedPos = $targetPosition + strlen($line3);
        $runner->assertEquals($expectedPos, $posAfterSeekRead,
            "Position after seek and read should match expected");

        $fileHandler->close();
    } finally {
        cleanupTempBz2Config($configFile);
        cleanupTempBz2Dir($bz2Filepath);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Graceful error when ext-bz2 missing
// ----------------------------------------------------------------------------
$runner->test('Throws RuntimeException when ext-bz2 is missing', function () use ($runner, $bz2Available) {
    if ($bz2Available) {
        // Cannot test missing extension when it's available
        // We'll verify the error message format instead by checking the code path exists
        $runner->skip('Cannot test missing extension error when ext-bz2 is available');
    }

    // Create a mock .bz2 file (just an empty file for testing)
    $tempDir = sys_get_temp_dir() . '/bigdump_bz2_test_uploads_' . uniqid();
    mkdir($tempDir, 0755, true);
    $bz2File = $tempDir . '/test.bz2';
    file_put_contents($bz2File, 'fake bz2 content');

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $exceptionThrown = false;
        $exceptionMessage = '';

        try {
            $fileHandler->open('test.bz2');
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }

        $runner->assertTrue($exceptionThrown, "Should throw RuntimeException when ext-bz2 missing");
        $runner->assertStringContains('bz2 extension', strtolower($exceptionMessage),
            "Error message should mention bz2 extension");
    } finally {
        cleanupTempBz2Config($configFile);
        unlink($bz2File);
        @rmdir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 5: EOF detection in BZ2 mode
// ----------------------------------------------------------------------------
$runner->test('EOF detection works correctly in BZ2 mode', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    // Create small test content
    $sqlContent = "Line 1\nLine 2\nLine 3\n";

    // Create temporary bz2 file
    $bz2Filepath = createTempBz2SqlFile($sqlContent, 'bz2');
    $tempDir = dirname($bz2Filepath);

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $filename = basename($bz2Filepath);
        $fileHandler->open($filename);

        // Should not be at EOF initially
        $runner->assertFalse($fileHandler->eof(), "Should not be at EOF initially");

        // Read all lines
        $lineCount = 0;
        while (($line = $fileHandler->readLine()) !== false) {
            $lineCount++;
        }

        $runner->assertEquals(3, $lineCount, "Should read 3 lines");

        // Should be at EOF after reading all lines
        $runner->assertTrue($fileHandler->eof(), "Should be at EOF after reading all lines");

        $fileHandler->close();
    } finally {
        cleanupTempBz2Config($configFile);
        cleanupTempBz2Dir($bz2Filepath);
    }
});

// ----------------------------------------------------------------------------
// Test 6: Mixed mode operations (ensure gzip still works alongside bz2)
// ----------------------------------------------------------------------------
$runner->test('GZip mode still works correctly alongside BZ2 support', function () use ($runner) {
    // Create test content
    $sqlContent = "-- Test GZip SQL file\n";
    $sqlContent .= "CREATE TABLE gztest (id INT PRIMARY KEY);\n";
    $sqlContent .= "INSERT INTO gztest VALUES (100);\n";

    // Create temporary gzip file
    $gzFilepath = createTempBz2SqlFile($sqlContent, 'gz');
    $tempDir = dirname($gzFilepath);

    $configFile = createTempBz2Config([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Open the gzip file
        $filename = basename($gzFilepath);
        $result = $fileHandler->open($filename);
        $runner->assertTrue($result, "Should successfully open GZip file");

        // Verify gzip mode is active (not bz2)
        $runner->assertTrue($fileHandler->isGzipMode(), "Should be in GZip mode");
        $runner->assertFalse($fileHandler->isBz2Mode(), "Should not be in BZ2 mode");

        // Read and verify content
        $lines = [];
        while (($line = $fileHandler->readLine()) !== false) {
            $lines[] = $line;
        }

        $runner->assertEquals(3, count($lines), "Should read 3 lines from GZip file");
        $runner->assertStringContains('gztest', $lines[1], "Should read correct content from GZip file");

        // Test seek works with gzip
        $fileHandler->seek(0);
        $firstLine = $fileHandler->readLine();
        $runner->assertStringContains('Test GZip', $firstLine, "GZip seek should work correctly");

        $fileHandler->close();

        // Verify modes are reset
        $runner->assertFalse($fileHandler->isGzipMode(), "GZip mode should be reset after close");
        $runner->assertFalse($fileHandler->isBz2Mode(), "BZ2 mode should be reset after close");
    } finally {
        cleanupTempBz2Config($configFile);
        cleanupTempBz2Dir($gzFilepath);
    }
});

// Output test results
exit($runner->summary());
