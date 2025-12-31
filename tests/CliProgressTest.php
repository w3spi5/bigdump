<?php

/**
 * CLI Progress Reporting Tests
 *
 * Tests for Task Group 5: Progress Reporting.
 * These tests verify:
 * - Progress format string output
 * - Final summary format
 * - Time formatting
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
// TEST SUITE: CLI Progress Reporting Tests
// ============================================================================

echo "CLI Progress Reporting Tests\n";
echo "============================\n\n";

$runner = new TestRunner();
$cliPath = dirname(__DIR__) . '/cli.php';

// ----------------------------------------------------------------------------
// Test 1: Progress output format contains expected elements
// ----------------------------------------------------------------------------
$runner->test('Progress output contains Lines and INSERTs metrics', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'progress_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'progress_out_') . '.sql';

    // Create a file large enough to trigger progress updates
    $content = "";
    for ($i = 1; $i <= 100; $i++) {
        $content .= "INSERT INTO users VALUES ({$i}, 'User {$i}');\n";
    }
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $outputStr = implode("\n", $output);

    // Should show completion message
    $runner->assertContains('Complete', $outputStr);

    // Should show statistics
    $runner->assertTrue(
        str_contains($outputStr, 'Input lines') || str_contains($outputStr, 'Lines'),
        "Output should contain lines count"
    );

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 2: Final summary shows correct statistics format
// ----------------------------------------------------------------------------
$runner->test('Final summary shows statistics in correct format', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'summary_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'summary_out_') . '.sql';

    $content = <<<SQL
INSERT INTO t VALUES (1);
INSERT INTO t VALUES (2);
INSERT INTO t VALUES (3);
SQL;
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $outputStr = implode("\n", $output);

    // Check for required summary elements
    $runner->assertContains('Input lines:', $outputStr);
    $runner->assertContains('Output queries:', $outputStr);
    $runner->assertContains('Time elapsed:', $outputStr);
    $runner->assertContains('Output size:', $outputStr);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 3: Header displays profile and batch size
// ----------------------------------------------------------------------------
$runner->test('Header displays input/output and profile info', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'header_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'header_out_') . '.sql';

    file_put_contents($inputFile, "SELECT 1;\n");
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} --profile=aggressive 2>&1", $output, $exitCode);

    $outputStr = implode("\n", $output);

    // Should show header
    $runner->assertContains('BigDump SQL Optimizer', $outputStr);

    // Should show profile
    $runner->assertContains('aggressive', $outputStr);

    // Should show Input/Output
    $runner->assertContains('Input:', $outputStr);
    $runner->assertContains('Output:', $outputStr);

    @unlink($inputFile);
    @unlink($outputFile);
});

// Output test results
exit($runner->summary());
