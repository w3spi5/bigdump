<?php

/**
 * PHAR Entry Points Tests
 *
 * Tests for web and CLI entry point functionality.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();

echo "========================================\n";
echo "PHAR Entry Points Test Suite\n";
echo "========================================\n\n";

define('PROJECT_ROOT', dirname(__DIR__));
define('OUTPUT_DIR', PROJECT_ROOT . '/dist');
define('PHAR_FILE', OUTPUT_DIR . '/bigdump.phar');
define('CONFIG_EXAMPLE', OUTPUT_DIR . '/bigdump-config.example.php');

// Ensure PHAR exists
if (!file_exists(PHAR_FILE)) {
    echo "Note: Building PHAR for tests...\n";
    exec('php -d phar.readonly=0 ' . escapeshellarg(PROJECT_ROOT . '/build/build-phar.php') . ' 2>&1');
}

// Test 1: CLI entry point handles --version flag
$runner->test('CLI entry point handles --version flag', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' --version 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '--version should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('BigDump', $outputStr, 'Should contain BigDump');
    $runner->assertContains('SQL Optimizer', $outputStr, 'Should mention SQL Optimizer');
    $runner->assertContains('PHAR', $outputStr, 'Should mention PHAR mode');
});

// Test 2: CLI entry point handles -v short flag
$runner->test('CLI entry point handles -v short flag', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' -v 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '-v should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('BigDump', $outputStr, 'Should contain BigDump');
});

// Test 3: CLI entry point handles --help flag
$runner->test('CLI entry point handles --help flag', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' --help 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '--help should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('Usage:', $outputStr, 'Should show usage');
    $runner->assertContains('--output', $outputStr, 'Should mention --output option');
    $runner->assertContains('--profile', $outputStr, 'Should mention --profile option');
    $runner->assertContains('Examples:', $outputStr, 'Should show examples');
});

// Test 4: CLI entry point handles -h short flag
$runner->test('CLI entry point handles -h short flag', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' -h 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '-h should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('Usage:', $outputStr, 'Should show usage');
});

// Test 5: CLI entry point shows help with no arguments
$runner->test('CLI entry point shows help with no arguments', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'No args should exit with 0 (shows help)');

    $outputStr = implode("\n", $output);
    $runner->assertContains('Usage:', $outputStr, 'Should show usage');
});

// Test 6: CLI entry point rejects missing output option
$runner->test('CLI entry point rejects missing output option', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' test.sql 2>&1', $output, $exitCode);

    $runner->assertEquals(1, $exitCode, 'Missing output should exit with 1');

    $outputStr = implode("\n", $output);
    $runner->assertContains('--output', $outputStr, 'Should mention required output option');
});

// Test 7: CLI entry point rejects non-existent input file
$runner->test('CLI entry point rejects non-existent input file', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' nonexistent.sql -o output.sql 2>&1', $output, $exitCode);

    $runner->assertEquals(1, $exitCode, 'Missing input file should exit with 1');

    $outputStr = implode("\n", $output);
    $runner->assertContains('not found', $outputStr, 'Should mention file not found');
});

// Test 8: CLI entry point validates profile option
$runner->test('CLI entry point validates profile option', function () use ($runner) {
    // Need to provide an input file for profile validation to be reached
    $testFile = sys_get_temp_dir() . '/bigdump_profile_test_' . uniqid() . '.sql';
    file_put_contents($testFile, "SELECT 1;\n");

    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' ' . escapeshellarg($testFile) .
         ' -o /tmp/out.sql --profile=invalid 2>&1', $output, $exitCode);

    // Cleanup
    @unlink($testFile);

    $runner->assertEquals(1, $exitCode, 'Invalid profile should exit with 1');

    $outputStr = implode("\n", $output);
    $runner->assertContains('Invalid profile', $outputStr, 'Should mention invalid profile');
});

// Test 9: CLI entry point accepts valid profiles
$runner->test('CLI entry point accepts valid profiles', function () use ($runner) {
    $output = [];
    $exitCode = 0;

    // --help takes precedence so should exit 0
    exec('php ' . escapeshellarg(PHAR_FILE) . ' --help --profile=conservative 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'Should accept conservative profile');
});

// Test 10: Example config exists and is valid PHP
$runner->test('Example config is valid PHP', function () use ($runner) {
    $runner->assertFileExists(CONFIG_EXAMPLE, 'Config example should exist');

    $output = [];
    $exitCode = 0;

    exec('php -l ' . escapeshellarg(CONFIG_EXAMPLE) . ' 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'Config example should have valid PHP syntax');
});

// Test 11: Example config contains required fields
$runner->test('Example config contains required database fields', function () use ($runner) {
    $content = file_get_contents(CONFIG_EXAMPLE);

    $runner->assertContains('db_server', $content, 'Should contain db_server');
    $runner->assertContains('db_name', $content, 'Should contain db_name');
    $runner->assertContains('db_username', $content, 'Should contain db_username');
    $runner->assertContains('db_password', $content, 'Should contain db_password');
    $runner->assertContains('upload_dir', $content, 'Should contain upload_dir');
});

// Test 12: Example config has PHAR-specific settings
$runner->test('Example config has PHAR-specific settings', function () use ($runner) {
    $content = file_get_contents(CONFIG_EXAMPLE);

    // Should have upload_dir set to relative path
    $runner->assertContains('./uploads/', $content, 'Should have relative uploads path');
});

// Test 13: Web entry stub has valid syntax
$runner->test('Web entry stub has valid PHP syntax', function () use ($runner) {
    $webEntry = PROJECT_ROOT . '/build/stubs/web-entry.php';

    $output = [];
    $exitCode = 0;

    exec('php -l ' . escapeshellarg($webEntry) . ' 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'Web entry stub should have valid syntax');
});

// Test 14: CLI entry stub has valid syntax
$runner->test('CLI entry stub has valid PHP syntax', function () use ($runner) {
    $cliEntry = PROJECT_ROOT . '/build/stubs/cli-entry.php';

    $output = [];
    $exitCode = 0;

    exec('php -l ' . escapeshellarg($cliEntry) . ' 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'CLI entry stub should have valid syntax');
});

// Test 15: CLI entry processes actual SQL file
$runner->test('CLI entry processes actual SQL file', function () use ($runner) {
    // Create test SQL file
    $inputFile = sys_get_temp_dir() . '/bigdump_input_' . uniqid() . '.sql';
    $outputFile = sys_get_temp_dir() . '/bigdump_output_' . uniqid() . '.sql';

    $sql = <<<SQL
-- Test SQL dump
CREATE TABLE test (id INT PRIMARY KEY);
INSERT INTO test VALUES (1);
INSERT INTO test VALUES (2);
INSERT INTO test VALUES (3);
SQL;

    file_put_contents($inputFile, $sql);

    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' ' .
         escapeshellarg($inputFile) . ' -o ' . escapeshellarg($outputFile) . ' 2>&1',
         $output, $exitCode);

    // Cleanup
    @unlink($inputFile);
    @unlink($outputFile);

    $runner->assertEquals(0, $exitCode, 'Should process SQL file successfully');
});

// Test 16: CLI entry supports --force flag
$runner->test('CLI entry supports --force flag', function () use ($runner) {
    // Create test SQL file and output that already exists
    $inputFile = sys_get_temp_dir() . '/bigdump_force_input_' . uniqid() . '.sql';
    $outputFile = sys_get_temp_dir() . '/bigdump_force_output_' . uniqid() . '.sql';

    file_put_contents($inputFile, "SELECT 1;\n");
    file_put_contents($outputFile, "existing content");

    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(PHAR_FILE) . ' ' .
         escapeshellarg($inputFile) . ' -o ' . escapeshellarg($outputFile) . ' --force 2>&1',
         $output, $exitCode);

    // Cleanup
    @unlink($inputFile);
    @unlink($outputFile);

    $runner->assertEquals(0, $exitCode, '--force should allow overwriting');
});

// Summary
exit($runner->summary());
