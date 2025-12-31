<?php

/**
 * CLI Integration Tests
 *
 * Tests for Task Group 7: Integration and Manual Testing.
 * These tests verify end-to-end CLI functionality:
 * - Processing of test fixture files
 * - Output matches expected batched result
 * - Compressed input processing
 * - --force overwrite behavior
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

use BigDump\Tests\TestRunner;

// ============================================================================
// TEST SUITE: CLI Integration Tests
// ============================================================================

echo "CLI Integration Tests\n";
echo "=====================\n\n";

$runner = new TestRunner();
$cliPath = dirname(__DIR__) . '/cli.php';
$fixturesDir = dirname(__DIR__) . '/tests/fixtures';

// ----------------------------------------------------------------------------
// Test 1: End-to-end processing of test fixture
// ----------------------------------------------------------------------------
$runner->test('Processes test fixture file end-to-end', function () use ($runner, $cliPath, $fixturesDir) {
    $inputFile = $fixturesDir . '/cli_test_input.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_int_') . '.sql';

    if (!file_exists($inputFile)) {
        throw new RuntimeException("Test fixture not found: {$inputFile}");
    }

    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "CLI should exit with code 0");
    $runner->assertFileExists($outputFile, "Output file should be created");

    $outputContent = file_get_contents($outputFile);

    // Check that INSERTs were batched (multiple value sets in single statement)
    $runner->assertContains('), (', $outputContent);

    // Check that non-INSERT statements are preserved
    $runner->assertContains('CREATE TABLE', $outputContent);
    $runner->assertContains('CREATE INDEX', $outputContent);
    $runner->assertContains('UPDATE', $outputContent);
    $runner->assertContains('SELECT COUNT', $outputContent);

    // Check INSERT IGNORE is properly handled
    $runner->assertContains('INSERT IGNORE', $outputContent);

    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 2: Output batches consecutive INSERTs correctly
// ----------------------------------------------------------------------------
$runner->test('Batches consecutive INSERTs into single statement', function () use ($runner, $cliPath, $fixturesDir) {
    $inputFile = $fixturesDir . '/cli_test_input.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_batch_') . '.sql';

    @unlink($outputFile);

    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $outputContent = file_get_contents($outputFile);

    // The 5 product INSERTs (1-5) should be batched into one statement
    // Count occurrences of Widget A through Gadget Y in the same INSERT
    $productInsert = '';
    foreach (explode("\n", $outputContent) as $line) {
        if (str_contains($line, 'Widget A')) {
            $productInsert = $line;
            break;
        }
    }

    $runner->assertNotEmpty($productInsert, "Should find batched product INSERT");
    $runner->assertContains('Widget B', $productInsert);
    $runner->assertContains('Widget C', $productInsert);
    $runner->assertContains('Gadget X', $productInsert);
    $runner->assertContains('Gadget Y', $productInsert);

    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 3: Processes gzip compressed input
// ----------------------------------------------------------------------------
$runner->test('Processes .sql.gz compressed input', function () use ($runner, $cliPath) {
    if (!function_exists('gzopen')) {
        echo "    (Skipped: gzip not available)\n";
        return;
    }

    $gzFile = tempnam(sys_get_temp_dir(), 'cli_gz_') . '.sql.gz';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_gz_out_') . '.sql';

    // Create gzip test file
    $content = <<<SQL
INSERT INTO t VALUES (1, 'gzip test 1');
INSERT INTO t VALUES (2, 'gzip test 2');
INSERT INTO t VALUES (3, 'gzip test 3');
SQL;
    $gz = gzopen($gzFile, 'wb');
    gzwrite($gz, $content);
    gzclose($gz);

    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$gzFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Should process gzip file successfully");

    $outputContent = file_get_contents($outputFile);
    $runner->assertContains('gzip test 1', $outputContent);
    $runner->assertContains('gzip test 2', $outputContent);
    $runner->assertContains('gzip test 3', $outputContent);

    // Should be batched
    $runner->assertContains('), (', $outputContent);

    @unlink($gzFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 4: Processes bz2 compressed input
// ----------------------------------------------------------------------------
$runner->test('Processes .sql.bz2 compressed input', function () use ($runner, $cliPath) {
    if (!function_exists('bzopen')) {
        echo "    (Skipped: bz2 extension not available)\n";
        return;
    }

    $bz2File = tempnam(sys_get_temp_dir(), 'cli_bz2_') . '.sql.bz2';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_bz2_out_') . '.sql';

    // Create bz2 test file
    $content = <<<SQL
INSERT INTO t VALUES (1, 'bz2 test 1');
INSERT INTO t VALUES (2, 'bz2 test 2');
SQL;
    $bz = bzopen($bz2File, 'w');
    bzwrite($bz, $content);
    bzclose($bz);

    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$bz2File} -o {$outputFile} 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Should process bz2 file successfully");

    $outputContent = file_get_contents($outputFile);
    $runner->assertContains('bz2 test 1', $outputContent);
    $runner->assertContains('bz2 test 2', $outputContent);

    @unlink($bz2File);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 5: --force overwrites existing file
// ----------------------------------------------------------------------------
$runner->test('--force overwrites existing output file', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_force_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_force_out_') . '.sql';

    file_put_contents($inputFile, "INSERT INTO t VALUES (1, 'force test');\n");
    file_put_contents($outputFile, "old content that should be replaced\n");

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} -f 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Should succeed with --force");

    $outputContent = file_get_contents($outputFile);
    $runner->assertContains('force test', $outputContent);
    $runner->assertNotContains('old content', $outputContent);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 6: Aggressive profile uses different batch size
// ----------------------------------------------------------------------------
$runner->test('Aggressive profile uses batch-size 5000', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_profile_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_profile_out_') . '.sql';

    file_put_contents($inputFile, "INSERT INTO t VALUES (1);\n");
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} --profile=aggressive 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Should process with aggressive profile");

    $outputStr = implode("\n", $output);
    $runner->assertContains('aggressive', $outputStr);
    $runner->assertContains('5000', $outputStr);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 7: Custom --batch-size overrides profile
// ----------------------------------------------------------------------------
$runner->test('Custom --batch-size overrides profile default', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_batch_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_batch_out_') . '.sql';

    file_put_contents($inputFile, "INSERT INTO t VALUES (1);\n");
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} --batch-size=3500 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Should process with custom batch-size");

    $outputStr = implode("\n", $output);
    $runner->assertContains('3500', $outputStr);

    @unlink($inputFile);
    @unlink($outputFile);
});

// Output test results
exit($runner->summary());
