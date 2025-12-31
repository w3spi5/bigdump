<?php

/**
 * CLI File Reader Tests
 *
 * Tests for Task Group 2: Standalone File Reading Adapter.
 * These tests verify:
 * - Opening .sql file by absolute path
 * - Opening .sql.gz file
 * - Opening .sql.bz2 file (with extension check)
 * - BOM removal on first line
 * - Error handling for non-existent file
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/tests/TestRunner.php';

// Autoloader for BigDump classes
spl_autoload_register(function (string $class): void {
    $prefix = 'BigDump\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use BigDump\Services\CliFileReader;
use BigDump\Tests\TestRunner;

// ============================================================================
// TEST SUITE: CLI File Reader Tests
// ============================================================================

echo "CLI File Reader Tests\n";
echo "=====================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Opening .sql file by absolute path
// ----------------------------------------------------------------------------
$runner->test('Opens .sql file by absolute path', function () use ($runner) {
    $sqlFile = tempnam(sys_get_temp_dir(), 'cli_reader_') . '.sql';
    file_put_contents($sqlFile, "SELECT 1;\nSELECT 2;\n");

    $reader = new CliFileReader($sqlFile);
    $reader->open();

    $line1 = $reader->readLine();
    $runner->assertContains('SELECT 1', $line1);

    $line2 = $reader->readLine();
    $runner->assertContains('SELECT 2', $line2);

    $reader->close();
    @unlink($sqlFile);
});

// ----------------------------------------------------------------------------
// Test 2: Opening .sql.gz file
// ----------------------------------------------------------------------------
$runner->test('Opens .sql.gz file', function () use ($runner) {
    if (!function_exists('gzopen')) {
        echo "    (Skipped: gzip not available)\n";
        return;
    }

    $sqlFile = tempnam(sys_get_temp_dir(), 'cli_reader_') . '.sql.gz';
    $content = "SELECT 'gzip test';\nSELECT 'second line';\n";

    // Create gzip file
    $gz = gzopen($sqlFile, 'wb');
    gzwrite($gz, $content);
    gzclose($gz);

    $reader = new CliFileReader($sqlFile);
    $reader->open();

    $line1 = $reader->readLine();
    $runner->assertContains('gzip test', $line1);

    $line2 = $reader->readLine();
    $runner->assertContains('second line', $line2);

    $reader->close();
    @unlink($sqlFile);
});

// ----------------------------------------------------------------------------
// Test 3: Opening .sql.bz2 file (with extension check)
// ----------------------------------------------------------------------------
$runner->test('Opens .sql.bz2 file if bz2 extension available', function () use ($runner) {
    if (!function_exists('bzopen')) {
        echo "    (Skipped: bz2 extension not available)\n";
        return;
    }

    $sqlFile = tempnam(sys_get_temp_dir(), 'cli_reader_') . '.sql.bz2';
    $content = "SELECT 'bz2 test';\n";

    // Create bz2 file
    $bz = bzopen($sqlFile, 'w');
    bzwrite($bz, $content);
    bzclose($bz);

    $reader = new CliFileReader($sqlFile);
    $reader->open();

    $line1 = $reader->readLine();
    $runner->assertContains('bz2 test', $line1);

    $reader->close();
    @unlink($sqlFile);
});

// ----------------------------------------------------------------------------
// Test 4: BOM removal on first line
// ----------------------------------------------------------------------------
$runner->test('Removes BOM from first line', function () use ($runner) {
    $sqlFile = tempnam(sys_get_temp_dir(), 'cli_reader_') . '.sql';

    // UTF-8 BOM: EF BB BF
    $bom = "\xEF\xBB\xBF";
    $content = $bom . "SELECT 'after bom';\n";
    file_put_contents($sqlFile, $content);

    $reader = new CliFileReader($sqlFile);
    $reader->open();

    $line1 = $reader->readLine();

    // Should NOT start with BOM bytes
    $runner->assertFalse(
        str_starts_with($line1, $bom),
        "First line should not start with BOM"
    );
    $runner->assertContains('SELECT', $line1);

    $reader->close();
    @unlink($sqlFile);
});

// ----------------------------------------------------------------------------
// Test 5: Error handling for non-existent file
// ----------------------------------------------------------------------------
$runner->test('Throws exception for non-existent file', function () use ($runner) {
    $nonExistent = '/tmp/non_existent_' . time() . '.sql';

    $exceptionThrown = false;
    try {
        $reader = new CliFileReader($nonExistent);
        $reader->open();
    } catch (RuntimeException $e) {
        $exceptionThrown = true;
        $runner->assertContains('not found', strtolower($e->getMessage()));
    }

    $runner->assertTrue($exceptionThrown, "Should throw RuntimeException for non-existent file");
});

// ----------------------------------------------------------------------------
// Test 6: getFileSize and getBytesRead tracking
// ----------------------------------------------------------------------------
$runner->test('Tracks file size and bytes read', function () use ($runner) {
    $sqlFile = tempnam(sys_get_temp_dir(), 'cli_reader_') . '.sql';
    $content = str_repeat("SELECT 1;\n", 100); // ~1000 bytes
    file_put_contents($sqlFile, $content);

    $reader = new CliFileReader($sqlFile);
    $reader->open();

    $fileSize = $reader->getFileSize();
    $runner->assertGreaterThan(900, $fileSize);

    // Read some lines
    $reader->readLine();
    $reader->readLine();

    $bytesRead = $reader->getBytesRead();
    $runner->assertGreaterThan(0, $bytesRead);

    $reader->close();
    @unlink($sqlFile);
});

// Output test results
exit($runner->summary());
