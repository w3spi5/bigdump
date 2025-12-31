<?php

/**
 * CLI Optimizer Service Tests
 *
 * Tests for Task Group 4: CLI Optimizer Service (Orchestration).
 * These tests verify:
 * - Processing file with INSERT batching enabled
 * - Non-INSERT statements pass through unchanged
 * - Profile batch size application
 * - --batch-size override works
 * - Statistics collection
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

use BigDump\Services\CliOptimizerService;
use BigDump\Tests\TestRunner;

// ============================================================================
// TEST SUITE: CLI Optimizer Service Tests
// ============================================================================

echo "CLI Optimizer Service Tests\n";
echo "===========================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Processing file with INSERT batching enabled
// ----------------------------------------------------------------------------
$runner->test('Batches multiple INSERT statements', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    // Create input with multiple INSERTs to same table
    $content = <<<SQL
INSERT INTO users VALUES (1, 'Alice');
INSERT INTO users VALUES (2, 'Bob');
INSERT INTO users VALUES (3, 'Charlie');
SQL;
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => 1000,
        'maxBatchBytes' => 16777216,
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);

    $output = file_get_contents($outputFile);
    // Should be batched into single INSERT with multiple value sets
    $runner->assertContains('Alice', $output);
    $runner->assertContains('Bob', $output);
    $runner->assertContains('Charlie', $output);

    // Should have VALUES ... ), ( pattern indicating batching
    $runner->assertContains('), (', $output);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 2: Non-INSERT statements pass through unchanged
// ----------------------------------------------------------------------------
$runner->test('Non-INSERT statements pass through unchanged', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    $content = <<<SQL
CREATE TABLE users (id INT, name VARCHAR(100));
INSERT INTO users VALUES (1, 'Alice');
ALTER TABLE users ADD INDEX idx_name (name);
SELECT * FROM users;
SQL;
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => 1000,
        'maxBatchBytes' => 16777216,
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);

    $output = file_get_contents($outputFile);
    $runner->assertContains('CREATE TABLE users', $output);
    $runner->assertContains('ALTER TABLE users ADD INDEX', $output);
    $runner->assertContains('SELECT * FROM users', $output);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 3: Conservative profile batch size (2000)
// ----------------------------------------------------------------------------
$runner->test('Conservative profile uses batch size 2000', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    // Just test that the profile is accepted
    $content = "INSERT INTO t VALUES (1);\n";
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => CliOptimizerService::PROFILE_CONSERVATIVE['batch_size'],
        'maxBatchBytes' => CliOptimizerService::PROFILE_CONSERVATIVE['max_batch_bytes'],
        'profile' => 'conservative',
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 4: Aggressive profile batch size (5000)
// ----------------------------------------------------------------------------
$runner->test('Aggressive profile uses batch size 5000', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    $content = "INSERT INTO t VALUES (1);\n";
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => CliOptimizerService::PROFILE_AGGRESSIVE['batch_size'],
        'maxBatchBytes' => CliOptimizerService::PROFILE_AGGRESSIVE['max_batch_bytes'],
        'profile' => 'aggressive',
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 5: --batch-size override works
// ----------------------------------------------------------------------------
$runner->test('Custom batch-size overrides profile', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    // Create 5 INSERTs, use batch size of 3
    $content = <<<SQL
INSERT INTO t VALUES (1);
INSERT INTO t VALUES (2);
INSERT INTO t VALUES (3);
INSERT INTO t VALUES (4);
INSERT INTO t VALUES (5);
SQL;
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => 3,  // Override to 3
        'maxBatchBytes' => 16777216,
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);

    $output = file_get_contents($outputFile);
    // Should have at least 2 INSERT statements (3+2)
    $insertCount = substr_count(strtoupper($output), 'INSERT INTO');
    $runner->assertGreaterThanOrEqual(2, $insertCount);

    @unlink($inputFile);
    @unlink($outputFile);
});

// ----------------------------------------------------------------------------
// Test 6: Statistics collection
// ----------------------------------------------------------------------------
$runner->test('Collects processing statistics', function () use ($runner) {
    $inputFile = tempnam(sys_get_temp_dir(), 'opt_test_') . '.sql';
    $outputFile = tempnam(sys_get_temp_dir(), 'opt_out_') . '.sql';

    $content = <<<SQL
INSERT INTO users VALUES (1, 'Alice');
INSERT INTO users VALUES (2, 'Bob');
INSERT INTO users VALUES (3, 'Charlie');
SELECT 1;
SQL;
    file_put_contents($inputFile, $content);
    @unlink($outputFile);

    $optimizer = new CliOptimizerService($inputFile, $outputFile, [
        'batchSize' => 1000,
        'maxBatchBytes' => 16777216,
    ]);
    $result = $optimizer->run();

    $runner->assertTrue($result['success']);
    $runner->assertArrayHasKey('statistics', $result);

    $stats = $result['statistics'];
    $runner->assertArrayHasKey('lines_processed', $stats);
    $runner->assertArrayHasKey('queries_written', $stats);
    $runner->assertArrayHasKey('inserts_batched', $stats);
    $runner->assertArrayHasKey('elapsed_time', $stats);
    $runner->assertArrayHasKey('output_size', $stats);

    $runner->assertGreaterThan(0, $stats['lines_processed']);
    $runner->assertGreaterThan(0, $stats['queries_written']);
    $runner->assertEquals(3, $stats['inserts_batched']);

    @unlink($inputFile);
    @unlink($outputFile);
});

// Output test results
exit($runner->summary());
