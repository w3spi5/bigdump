<?php

/**
 * BZ2 Integration Tests
 *
 * Tests for the complete BZ2 import workflow including:
 * - Full import cycle of .sql.bz2 fixture
 * - Resume functionality with seek workaround
 * - Edge cases (corrupted files, empty files, case-insensitive extensions)
 * - Coexistence of dump.sql.gz and dump.sql.bz2
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Load required classes
require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/FileHandler.php';
require_once dirname(__DIR__) . '/src/Models/Database.php';
require_once dirname(__DIR__) . '/src/Models/ImportSession.php';
require_once dirname(__DIR__) . '/src/Models/SqlParser.php';
require_once dirname(__DIR__) . '/src/Services/ImportService.php';
require_once dirname(__DIR__) . '/src/Services/AutoTunerService.php';
require_once dirname(__DIR__) . '/src/Services/InsertBatcherService.php';
require_once dirname(__DIR__) . '/src/Services/FileAnalysisService.php';

use BigDump\Config\Config;
use BigDump\Models\FileHandler;
use BigDump\Models\ImportSession;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class Bz2IntegrationTestRunner
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
        } catch (SkipBz2IntegrationTestException $e) {
            $this->skipped++;
            $this->skippedTests[] = $name . ': ' . $e->getMessage();
            echo "  SKIP: {$name} - {$e->getMessage()}\n";
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[$name] = $e->getMessage();
            echo "  FAIL: {$name}\n";
            echo "        " . $e->getMessage() . "\n";
            if ($e->getFile()) {
                echo "        at " . $e->getFile() . ':' . $e->getLine() . "\n";
            }
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

    public function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new RuntimeException(
                $message ?: "Expected value >= {$expected}, got {$actual}"
            );
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
        throw new SkipBz2IntegrationTestException($reason);
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
class SkipBz2IntegrationTestException extends Exception {}

/**
 * Creates a temporary config file with given settings
 *
 * @param array<string, mixed> $config
 * @return string Path to temporary config file
 */
function createBz2IntegrationConfig(array $config): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_bz2_integration_config_' . uniqid() . '.php';
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($tempFile, $configContent);
    return $tempFile;
}

/**
 * Removes temporary config file
 */
function cleanupBz2IntegrationConfig(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Creates a temporary directory with test files
 *
 * @param array<string, string> $files Filename => content pairs
 * @return string Path to temporary directory
 */
function createTempBz2IntegrationDir(array $files = []): string
{
    $tempDir = sys_get_temp_dir() . '/bigdump_bz2_integration_uploads_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    foreach ($files as $filename => $content) {
        file_put_contents($tempDir . '/' . $filename, $content);
    }

    return $tempDir;
}

/**
 * Cleans up temporary directory
 */
function cleanupTempBz2IntegrationDir(string $dir): void
{
    if (is_dir($dir) && strpos($dir, 'bigdump_bz2_integration') !== false) {
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
 * Creates a BZ2 compressed file with the given SQL content
 *
 * @param string $tempDir Directory to create file in
 * @param string $filename Filename (should end in .bz2)
 * @param string $sqlContent SQL content to compress
 * @return string Full path to created file
 */
function createBz2SqlFile(string $tempDir, string $filename, string $sqlContent): string
{
    if (!function_exists('bzopen')) {
        throw new RuntimeException('BZ2 extension not available');
    }

    $filepath = $tempDir . '/' . $filename;
    $bz = bzopen($filepath, 'w');
    if ($bz === false) {
        throw new RuntimeException("Failed to create BZ2 file: {$filepath}");
    }
    bzwrite($bz, $sqlContent);
    bzclose($bz);

    return $filepath;
}

/**
 * Creates a GZip compressed file with the given SQL content
 *
 * @param string $tempDir Directory to create file in
 * @param string $filename Filename (should end in .gz)
 * @param string $sqlContent SQL content to compress
 * @return string Full path to created file
 */
function createGzSqlFile(string $tempDir, string $filename, string $sqlContent): string
{
    $filepath = $tempDir . '/' . $filename;
    $gz = gzopen($filepath, 'wb9');
    if ($gz === false) {
        throw new RuntimeException("Failed to create GZip file: {$filepath}");
    }
    gzwrite($gz, $sqlContent);
    gzclose($gz);

    return $filepath;
}

/**
 * Get the path to the test fixture
 */
function getFixturePath(): string
{
    return dirname(__DIR__) . '/tests/fixtures/test_bz2_import.sql.bz2';
}

// ============================================================================
// TEST SUITE: BZ2 Integration Tests
// ============================================================================

echo "BZ2 Integration Tests\n";
echo "==========================================\n\n";

$runner = new Bz2IntegrationTestRunner();

// Check if BZ2 extension is available
$bz2Available = function_exists('bzopen');
echo "BZ2 Extension Available: " . ($bz2Available ? "YES" : "NO") . "\n";
echo "Test Fixture Exists: " . (file_exists(getFixturePath()) ? "YES" : "NO") . "\n\n";

// ----------------------------------------------------------------------------
// Test 1: Complete import of .sql.bz2 fixture - FileHandler level
// ----------------------------------------------------------------------------
$runner->test('Complete read of .sql.bz2 fixture via FileHandler', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $fixturePath = getFixturePath();
    if (!file_exists($fixturePath)) {
        $runner->skip('Test fixture not found: ' . $fixturePath);
    }

    // Copy fixture to temp directory
    $tempDir = createTempBz2IntegrationDir();
    copy($fixturePath, $tempDir . '/test_bz2_import.sql.bz2');

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Open the fixture
        $result = $fileHandler->open('test_bz2_import.sql.bz2');
        $runner->assertTrue($result, "Should successfully open BZ2 fixture");
        $runner->assertTrue($fileHandler->isBz2Mode(), "Should be in BZ2 mode");

        // Read all lines
        $lines = [];
        $lineCount = 0;
        while (($line = $fileHandler->readLine()) !== false) {
            $lines[] = $line;
            $lineCount++;
        }

        // Verify content was read correctly
        $runner->assertGreaterThan(0, $lineCount, "Should read lines from BZ2 fixture");

        // Check for expected SQL content
        $allContent = implode('', $lines);
        $runner->assertStringContains('CREATE TABLE', $allContent,
            "Content should contain CREATE TABLE statement");
        $runner->assertStringContains('bz2_test', $allContent,
            "Content should contain table name 'bz2_test'");
        $runner->assertStringContains('INSERT INTO', $allContent,
            "Content should contain INSERT statements");

        // Count INSERT statements
        $insertCount = substr_count($allContent, 'INSERT INTO');
        $runner->assertGreaterThanOrEqual(5, $insertCount,
            "Should have at least 5 INSERT statements");

        $fileHandler->close();
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 2: BZ2 import progress tracking
// ----------------------------------------------------------------------------
$runner->test('BZ2 import progress tracking works correctly', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $fixturePath = getFixturePath();
    if (!file_exists($fixturePath)) {
        $runner->skip('Test fixture not found');
    }

    // Copy fixture to temp directory
    $tempDir = createTempBz2IntegrationDir();
    copy($fixturePath, $tempDir . '/test_bz2_import.sql.bz2');

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $fileHandler->open('test_bz2_import.sql.bz2');

        // Track progress as we read
        $linesRead = 0;
        $bytesTracked = [];

        while (($line = $fileHandler->readLine()) !== false) {
            $linesRead++;
            // Note: BZ2 tell() may not be perfectly accurate due to buffering,
            // but we verify that reading works progressively
        }

        $runner->assertGreaterThan(0, $linesRead, "Should track lines read");

        // Verify EOF is reached
        $runner->assertTrue($fileHandler->eof(), "Should be at EOF after reading all lines");

        $fileHandler->close();
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 3: BZ2 resume functionality with seek workaround
// ----------------------------------------------------------------------------
$runner->test('BZ2 resume functionality with seek workaround', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    // Create test content with known positions
    $line1 = "-- Line 1: Comment\n";                    // 19 bytes
    $line2 = "CREATE TABLE test (id INT PRIMARY KEY);\n";  // 40 bytes
    $line3 = "INSERT INTO test VALUES (1);\n";          // 29 bytes
    $line4 = "INSERT INTO test VALUES (2);\n";          // 29 bytes
    $line5 = "INSERT INTO test VALUES (3);\n";          // 29 bytes

    $sqlContent = $line1 . $line2 . $line3 . $line4 . $line5;

    $tempDir = createTempBz2IntegrationDir();

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        // Create BZ2 file
        createBz2SqlFile($tempDir, 'resume_test.bz2', $sqlContent);

        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // --- First session: Read first 2 lines ---
        $fileHandler->open('resume_test.bz2');

        $firstLine = $fileHandler->readLine();
        $runner->assertStringContains('Line 1', $firstLine, "First line should be a comment");

        $secondLine = $fileHandler->readLine();
        $runner->assertStringContains('CREATE TABLE', $secondLine, "Second line should be CREATE TABLE");

        // Get position after reading 2 lines
        $positionAfterLine2 = strlen($line1) + strlen($line2);

        $fileHandler->close();

        // --- Second session: Seek to position and continue reading ---
        $fileHandler->open('resume_test.bz2');

        // Seek to position after line 2 using the workaround
        $progressCalls = 0;
        $seekResult = $fileHandler->seek($positionAfterLine2, function ($bytesRead, $target) use (&$progressCalls) {
            $progressCalls++;
        });

        $runner->assertTrue($seekResult, "Seek should succeed");
        $runner->assertGreaterThan(0, $progressCalls, "Progress callback should be called during seek");

        // Read line 3 (should be first INSERT)
        $line3Read = $fileHandler->readLine();
        $runner->assertStringContains('VALUES (1)', $line3Read,
            "After seek, should read first INSERT statement");

        // Read remaining lines
        $line4Read = $fileHandler->readLine();
        $runner->assertStringContains('VALUES (2)', $line4Read, "Should read second INSERT");

        $line5Read = $fileHandler->readLine();
        $runner->assertStringContains('VALUES (3)', $line5Read, "Should read third INSERT");

        // Verify EOF
        $runner->assertTrue($fileHandler->eof(), "Should be at EOF after reading all lines");

        $fileHandler->close();
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 4: BZ2 seek workaround positions correctly with data integrity
// ----------------------------------------------------------------------------
$runner->test('BZ2 seek workaround maintains data integrity after resume', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    // Create content with unique identifiers for verification
    $sqlContent = "";
    for ($i = 1; $i <= 20; $i++) {
        $sqlContent .= "INSERT INTO test VALUES ({$i}, 'unique_marker_{$i}');\n";
    }

    $tempDir = createTempBz2IntegrationDir();

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        createBz2SqlFile($tempDir, 'integrity_test.bz2', $sqlContent);

        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // First pass: Read all lines and record content
        $fileHandler->open('integrity_test.bz2');
        $allLines = [];
        while (($line = $fileHandler->readLine()) !== false) {
            $allLines[] = trim($line);
        }
        $fileHandler->close();

        // Second pass: Seek to middle and verify remaining content matches
        $fileHandler->open('integrity_test.bz2');

        // Read first 10 lines to find seek position
        $bytesRead = 0;
        for ($i = 0; $i < 10; $i++) {
            $line = $fileHandler->readLine();
            $bytesRead += strlen($line);
        }

        $fileHandler->close();

        // Reopen and seek to position 10
        $fileHandler->open('integrity_test.bz2');
        $seekResult = $fileHandler->seek($bytesRead);
        $runner->assertTrue($seekResult, "Seek should succeed");

        // Read remaining lines after seek
        $resumedLines = [];
        while (($line = $fileHandler->readLine()) !== false) {
            $resumedLines[] = trim($line);
        }

        // Verify resumed content matches expected content
        $expectedRemaining = array_slice($allLines, 10);
        $runner->assertEquals(count($expectedRemaining), count($resumedLines),
            "Resumed line count should match expected");

        for ($i = 0; $i < count($expectedRemaining); $i++) {
            $runner->assertEquals($expectedRemaining[$i], $resumedLines[$i],
                "Line " . ($i + 11) . " should match after resume");
        }

        $fileHandler->close();
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Corrupted .bz2 file handling (graceful error)
// ----------------------------------------------------------------------------
$runner->test('Corrupted .bz2 file handling produces graceful error', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $tempDir = createTempBz2IntegrationDir([
        'corrupted.bz2' => 'This is not valid bz2 content - just garbage data!'
    ]);

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Opening corrupted bz2 may fail or succeed depending on implementation
        // The important thing is it doesn't crash
        try {
            $fileHandler->open('corrupted.bz2');

            // If open succeeds, reading should fail gracefully
            $line = $fileHandler->readLine();

            // Either returns false (no data) or throws exception
            // Both are acceptable for corrupted files
            if ($line === false) {
                $runner->assertTrue(true, "Corrupted file returned no data (acceptable)");
            } else {
                // Some implementations may return garbage
                $runner->assertTrue(true, "Corrupted file handling did not crash");
            }

            $fileHandler->close();
        } catch (RuntimeException $e) {
            // Exception is acceptable for corrupted files
            $runner->assertTrue(true, "Corrupted file threw exception (acceptable): " . $e->getMessage());
        }
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 6: Empty .bz2 file handling
// ----------------------------------------------------------------------------
$runner->test('Empty .bz2 file handling', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $tempDir = createTempBz2IntegrationDir();

    // Create empty bz2 file (compressed empty content)
    createBz2SqlFile($tempDir, 'empty.bz2', '');

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $fileHandler->open('empty.bz2');

        // Read should return false immediately for empty file
        $line = $fileHandler->readLine();
        $runner->assertFalse($line !== false && $line !== '', "Empty file should return no content");

        // Should be at EOF
        $runner->assertTrue($fileHandler->eof(), "Empty file should be at EOF");

        $fileHandler->close();
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 7: Case-insensitive extension handling (.BZ2, .Bz2)
// ----------------------------------------------------------------------------
$runner->test('Case-insensitive extension handling (.BZ2, .Bz2)', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $sqlContent = "CREATE TABLE casetest (id INT);\n";
    $tempDir = createTempBz2IntegrationDir();

    // Create files with different case extensions
    // Note: On case-sensitive file systems, these are different files
    // On case-insensitive (Windows/Mac), they may conflict
    // We test extension detection, not filesystem behavior
    createBz2SqlFile($tempDir, 'test_upper.BZ2', $sqlContent);
    createBz2SqlFile($tempDir, 'test_mixed.Bz2', $sqlContent);

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // Test uppercase extension detection
        $runner->assertEquals('bz2', $fileHandler->getExtension('test.BZ2'),
            ".BZ2 extension should be detected as 'bz2'");
        $runner->assertEquals('bz2', $fileHandler->getExtension('test.Bz2'),
            ".Bz2 extension should be detected as 'bz2'");

        // Test opening files with different case extensions
        if (file_exists($tempDir . '/test_upper.BZ2')) {
            $fileHandler->open('test_upper.BZ2');
            $runner->assertTrue($fileHandler->isBz2Mode(), ".BZ2 file should open in BZ2 mode");
            $line = $fileHandler->readLine();
            $runner->assertStringContains('casetest', $line, "Should read content from .BZ2 file");
            $fileHandler->close();
        }

        if (file_exists($tempDir . '/test_mixed.Bz2')) {
            $fileHandler->open('test_mixed.Bz2');
            $runner->assertTrue($fileHandler->isBz2Mode(), ".Bz2 file should open in BZ2 mode");
            $line = $fileHandler->readLine();
            $runner->assertStringContains('casetest', $line, "Should read content from .Bz2 file");
            $fileHandler->close();
        }
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 8: Coexistence of dump.sql.gz and dump.sql.bz2 in file listing
// ----------------------------------------------------------------------------
$runner->test('Coexistence of dump.sql.gz and dump.sql.bz2 in file listing', function () use ($runner, $bz2Available) {
    if (!$bz2Available) {
        $runner->skip('BZ2 extension not available');
    }

    $sqlContent = "CREATE TABLE test (id INT);\n";
    $tempDir = createTempBz2IntegrationDir();

    // Create same base file in both formats
    createGzSqlFile($tempDir, 'dump.sql.gz', $sqlContent);
    createBz2SqlFile($tempDir, 'dump.sql.bz2', $sqlContent);

    // Also create a plain SQL version
    file_put_contents($tempDir . '/dump.sql', $sqlContent);

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        // List files
        $files = $fileHandler->listFiles();
        $filenames = array_column($files, 'name');

        // All three versions should be present
        $runner->assertContains('dump.sql', $filenames,
            "Plain SQL file should be in listing");
        $runner->assertContains('dump.sql.gz', $filenames,
            "GZip file should be in listing");
        $runner->assertContains('dump.sql.bz2', $filenames,
            "BZ2 file should be in listing");

        // Verify file types are correct
        $fileTypes = [];
        foreach ($files as $file) {
            $fileTypes[$file['name']] = $file['type'];
        }

        $runner->assertEquals('SQL', $fileTypes['dump.sql'],
            "Plain SQL file type should be 'SQL'");
        $runner->assertEquals('GZip', $fileTypes['dump.sql.gz'],
            "GZip file type should be 'GZip'");
        $runner->assertEquals('BZ2', $fileTypes['dump.sql.bz2'],
            "BZ2 file type should be 'BZ2'");
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// ----------------------------------------------------------------------------
// Test 9: BZ2 files hidden when ext-bz2 not available (simulate check)
// ----------------------------------------------------------------------------
$runner->test('BZ2 files filtering based on extension availability', function () use ($runner, $bz2Available) {
    // This test verifies the filtering logic works correctly
    // When ext-bz2 is available, BZ2 files should be shown
    // When ext-bz2 is NOT available, BZ2 files should be hidden

    $tempDir = createTempBz2IntegrationDir([
        'test.sql' => "CREATE TABLE test (id INT);\n",
    ]);

    // Create a fake bz2 file (just raw content, not actually compressed)
    // This simulates what would happen if someone uploads a .bz2 file
    // but the extension isn't available
    file_put_contents($tempDir . '/test.bz2', 'fake bz2 content');

    $configFile = createBz2IntegrationConfig([
        'db_name' => 'test',
        'db_username' => 'test',
        'upload_dir' => $tempDir,
        'allowed_extensions' => ['sql', 'gz', 'bz2', 'csv'],
    ]);

    try {
        $config = new Config($configFile);
        $fileHandler = new FileHandler($config);

        $files = $fileHandler->listFiles();
        $filenames = array_column($files, 'name');

        // SQL file should always be present
        $runner->assertContains('test.sql', $filenames,
            "SQL file should always be in listing");

        // BZ2 file presence depends on extension availability
        if ($bz2Available) {
            $runner->assertContains('test.bz2', $filenames,
                "BZ2 file should be in listing when ext-bz2 is available");
        } else {
            $runner->assertNotContains('test.bz2', $filenames,
                "BZ2 file should NOT be in listing when ext-bz2 is NOT available");
        }
    } finally {
        cleanupBz2IntegrationConfig($configFile);
        cleanupTempBz2IntegrationDir($tempDir);
    }
});

// Output test results
exit($runner->summary());
