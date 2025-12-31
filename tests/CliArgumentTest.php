<?php

/**
 * CLI Argument Parsing Tests
 *
 * Tests for Task Group 1: CLI Entry Point and Argument Parsing.
 * These tests verify:
 * - Mandatory --output validation
 * - Input file existence validation
 * - --force flag behavior
 * - --profile validation
 * - --batch-size parsing
 * - --help output
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/tests/TestRunner.php';

use BigDump\Tests\TestRunner;

// ============================================================================
// TEST SUITE: CLI Argument Parsing Tests
// ============================================================================

echo "CLI Argument Parsing Tests\n";
echo "==========================\n\n";

$runner = new TestRunner();

$cliPath = dirname(__DIR__) . '/cli.php';

// ----------------------------------------------------------------------------
// Test 1: Missing --output flag shows error and exits with code 1
// ----------------------------------------------------------------------------
$runner->test('Missing --output flag shows error', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_test_') . '.sql';
    file_put_contents($inputFile, "SELECT 1;");

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} 2>&1", $output, $exitCode);

    @unlink($inputFile);

    $runner->assertEquals(1, $exitCode, "Exit code should be 1 for missing --output");
    $outputStr = implode("\n", $output);
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'output') || str_contains(strtolower($outputStr), 'required'),
        "Output should mention missing output option"
    );
});

// ----------------------------------------------------------------------------
// Test 2: Non-existent input file shows error
// ----------------------------------------------------------------------------
$runner->test('Non-existent input file shows error', function () use ($runner, $cliPath) {
    $nonExistent = '/tmp/non_existent_file_' . time() . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_out_') . '.sql';

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$nonExistent} -o {$outputFile} 2>&1", $output, $exitCode);

    @unlink($outputFile);

    $runner->assertEquals(1, $exitCode, "Exit code should be 1 for non-existent file");
    $outputStr = implode("\n", $output);
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'not found') ||
        str_contains(strtolower($outputStr), 'exist') ||
        str_contains(strtolower($outputStr), 'cannot'),
        "Output should mention file not found"
    );
});

// ----------------------------------------------------------------------------
// Test 3: --force flag allows overwriting existing output
// ----------------------------------------------------------------------------
$runner->test('--force flag allows overwriting existing output', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_out_') . '.sql';

    // Create input file with simple content
    file_put_contents($inputFile, "INSERT INTO t VALUES (1);\n");

    // Create existing output file
    file_put_contents($outputFile, "existing content");

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} -f 2>&1", $output, $exitCode);

    // Should succeed with --force
    $runner->assertEquals(0, $exitCode, "Exit code should be 0 with --force");

    // Output file should be overwritten
    $content = file_get_contents($outputFile);
    $runner->assertFalse(
        str_contains($content, 'existing content'),
        "Output file should be overwritten"
    );

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 4: Invalid --profile value shows error
// ----------------------------------------------------------------------------
$runner->test('Invalid --profile value shows error', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_out_') . '.sql';

    file_put_contents($inputFile, "SELECT 1;");

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} --profile=invalid 2>&1", $output, $exitCode);

    @unlink($inputFile);
    @unlink($outputFile);

    $runner->assertEquals(1, $exitCode, "Exit code should be 1 for invalid profile");
    $outputStr = implode("\n", $output);
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'profile') ||
        str_contains(strtolower($outputStr), 'conservative') ||
        str_contains(strtolower($outputStr), 'aggressive'),
        "Output should mention valid profile options"
    );
});

// ----------------------------------------------------------------------------
// Test 5: --batch-size numeric parsing
// ----------------------------------------------------------------------------
$runner->test('--batch-size accepts numeric value', function () use ($runner, $cliPath) {
    $inputFile = tempnam(sys_get_temp_dir(), 'cli_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'cli_out_') . '.sql';

    file_put_contents($inputFile, "INSERT INTO t VALUES (1);\n");

    // Ensure output doesn't exist
    @unlink($outputFile);

    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} {$inputFile} -o {$outputFile} --batch-size=3000 2>&1", $output, $exitCode);

    $runner->assertEquals(0, $exitCode, "Exit code should be 0 with valid batch-size");

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 6: --help displays usage information
// ----------------------------------------------------------------------------
$runner->test('--help displays usage information', function () use ($runner, $cliPath) {
    $output = [];
    $exitCode = 0;
    exec("php {$cliPath} --help 2>&1", $output, $exitCode);

    $outputStr = implode("\n", $output);

    $runner->assertEquals(0, $exitCode, "Exit code should be 0 for --help");
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'usage'),
        "Help should contain usage information"
    );
    $runner->assertTrue(
        str_contains(strtolower($outputStr), '--output') || str_contains($outputStr, '-o'),
        "Help should mention --output option"
    );
    $runner->assertTrue(
        str_contains(strtolower($outputStr), '--force') || str_contains($outputStr, '-f'),
        "Help should mention --force option"
    );
    $runner->assertTrue(
        str_contains(strtolower($outputStr), 'conservative'),
        "Help should mention conservative profile"
    );
});

// Output test results
exit($runner->summary());
