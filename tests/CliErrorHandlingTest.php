<?php

/**
 * CLI Error Handling Tests
 *
 * Tests for Task Group 6: Error Handling and Cleanup.
 * These tests verify:
 * - Partial output cleanup on error
 * - Readable error messages
 * - Exit codes for different error types
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
// TEST SUITE: CLI Error Handling Tests
// ============================================================================

echo "CLI Error Handling Tests\n";
echo "========================\n\n";

$runner = new TestRunner();
$cliPath = dirname(__DIR__) . '/cli.php';

// ----------------------------------------------------------------------------
// Test 1: Output file protected unless --force specified
// ----------------------------------------------------------------------------
$runner->test('Fails if output file exists without --force', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'err_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'err_out_') . '.sql';

    file_put_contents($inputFile, "SELECT 1;\n");
    file_put_contents($outputFile, "existing content\n");

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    // Should fail with exit code 1 (user error)
    $runner->assertEquals(1, $exitCode, "Should exit with code 1 when output exists");

    $outputStr = implode("\n", $output);
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'exists') || str_contains(strtolower($outputStr), 'force'),
        "Error should mention file exists or --force option"
    );

    // Existing file should not be modified
    $runner->assertEquals("existing content\n", file_get_contents($outputFile));

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 2: Exit code 1 for user errors (invalid arguments)
// ----------------------------------------------------------------------------
$runner->test('Exit code 1 for user errors', function () use ($runner, $cliPath) {
    // Test: missing input file
    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} 2>&1", $output, $exitCode);
    $runner->assertEquals(1, $exitCode, "Missing input should be exit code 1");

    // Test: non-existent input file
    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} /nonexistent/file.sql -o /tmp/out.sql 2>&1", $output, $exitCode);
    $runner->assertEquals(1, $exitCode, "Non-existent file should be exit code 1");

    // Test: invalid profile
    $inputFile = tempnam(sys_get_temp_dir(), 'err_test_') . '.sql';
    file_put_contents($inputFile, "SELECT 1;\n");
    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o /tmp/out.sql --profile=invalid 2>&1", $output, $exitCode);
    $runner->assertEquals(1, $exitCode, "Invalid profile should be exit code 1");

    @unlink($inputFile);
});

// ----------------------------------------------------------------------------
// Test 3: Readable error messages for common failures
// ----------------------------------------------------------------------------
$runner->test('Error messages are user-friendly', function () use ($runner, $cliPath) {
    // Missing input file
    $output = [];
    exec("php {$cliPath} /tmp/nonexistent_12345.sql -o /tmp/out.sql 2>&1", $output, $exitCode);
    $outputStr = implode("\n", $output);

    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'not found') ||
        str_contains(strtolower($outputStr), 'error') ||
        str_contains(strtolower($outputStr), 'cannot'),
        "Error message should be readable"
    );

    // Missing --output
    $inputFile = tempnam(sys_get_temp_dir(), 'err_test_') . '.sql';
    file_put_contents($inputFile, "SELECT 1;\n");
    $output = [];
    exec("php {$cliPath} {$inputFile} 2>&1", $output, $exitCode);
    $outputStr = implode("\n", $output);

    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'output') ||
        str_contains(strtolower($outputStr), 'required'),
        "Error message should mention missing output option"
    );

    @unlink($inputFile);
});

// ----------------------------------------------------------------------------
// Test 4: Unsupported file extension shows error
// ----------------------------------------------------------------------------
$runner->test('Unsupported file extension shows error', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'err_test_') . '.txt';
    $outputFile = tempnam(sys_get_temp_dir(), 'err_out_') . '.sql';

    file_put_contents($inputFile, "SELECT 1;\n");
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} 2>&1", $output, $exitCode);

    $runner->assertEquals(1, $exitCode, "Unsupported extension should be exit code 1");
    $outputStr = implode("\n", $output);
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'unsupported') ||
        str_contains(strtolower($outputStr), 'extension') ||
        str_contains(strtolower($outputStr), 'type'),
        "Error should mention unsupported file type"
    );

    @unlink($inputFile);
    @unlink($outputFile);
});

// Output test results
exit($runner->summary());
