<?php

/**
 * INSERT Batching Enhancements Tests
 *
 * Tests for the INSERT batching improvements introduced in Task Group 2.
 * These tests verify:
 * - Increased batch size limits (2000/5000 based on profile)
 * - Configurable max_batch_bytes (16MB/32MB)
 * - INSERT IGNORE statement batching
 * - Adaptive batch sizing based on average row size
 * - Batch efficiency metrics accuracy
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/src/Services/InsertBatcherService.php';

use BigDump\Services\InsertBatcherService;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class TestRunner
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

    public function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than {$expected}, got {$actual}"
            );
        }
    }

    public function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual < $expected) {
            throw new RuntimeException(
                $message ?: "Expected value greater than or equal to {$expected}, got {$actual}"
            );
        }
    }

    public function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if ($actual > $expected) {
            throw new RuntimeException(
                $message ?: "Expected value less than or equal to {$expected}, got {$actual}"
            );
        }
    }

    public function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new RuntimeException(
                $message ?: "Expected string to contain '{$needle}'"
            );
        }
    }

    public function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new RuntimeException($message ?: "Expected non-null value");
        }
    }

    public function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new RuntimeException(
                $message ?: "Expected array to have key '{$key}'"
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

// ============================================================================
// TEST SUITE: INSERT Batching Enhancements
// ============================================================================

echo "INSERT Batching Enhancements Tests\n";
echo "===================================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Configurable batch size limits (2000/5000 based on profile)
// ----------------------------------------------------------------------------
$runner->test('Batch size limits are configurable via constructor', function () use ($runner) {
    // Conservative profile: batch size 2000
    $batcher2000 = new InsertBatcherService(2000);
    $stats = $batcher2000->getStatistics();
    $runner->assertEquals(2000, $stats['batch_size']);

    // Aggressive profile: batch size 5000
    $batcher5000 = new InsertBatcherService(5000);
    $stats5000 = $batcher5000->getStatistics();
    $runner->assertEquals(5000, $stats5000['batch_size']);

    // Test that batch flushes at the configured limit
    $batcher10 = new InsertBatcherService(10); // Small for testing
    for ($i = 1; $i <= 10; $i++) {
        $result = $batcher10->process("INSERT INTO t VALUES ({$i});");
        if ($i < 10) {
            // Should accumulate without flushing
            $runner->assertEquals([], $result['queries'], "Should not flush before limit (i={$i})");
        }
    }
    // 11th INSERT should trigger flush of previous 10
    $result = $batcher10->process("INSERT INTO t VALUES (11);");
    $runner->assertEquals(1, count($result['queries']), "Should flush at batch limit");
    $runner->assertContains('VALUES (1),', $result['queries'][0]);
});

// ----------------------------------------------------------------------------
// Test 2: Configurable max_batch_bytes (16MB/32MB)
// ----------------------------------------------------------------------------
$runner->test('max_batch_bytes is configurable via constructor', function () use ($runner) {
    // Small max bytes for testing: 100 bytes
    $batcher = new InsertBatcherService(1000, 100);

    // Create an INSERT with ~40 byte values
    $insert1 = "INSERT INTO t VALUES ('this is a test value 1234567890');";
    $insert2 = "INSERT INTO t VALUES ('another test value abcdefghij');";
    $insert3 = "INSERT INTO t VALUES ('third value that should flush');";

    $result1 = $batcher->process($insert1);
    $runner->assertEquals([], $result1['queries'], "First INSERT should accumulate");

    $result2 = $batcher->process($insert2);
    // Second should still accumulate (combined ~80 bytes < 100)
    $runner->assertEquals([], $result2['queries'], "Second INSERT should accumulate");

    $result3 = $batcher->process($insert3);
    // Third should trigger flush due to byte limit
    $runner->assertEquals(1, count($result3['queries']), "Should flush at byte limit");

    // Verify the flushed query contains batched values
    $runner->assertContains("test value 1234567890", $result3['queries'][0]);
    $runner->assertContains("test value abcdefghij", $result3['queries'][0]);
});

// ----------------------------------------------------------------------------
// Test 3: INSERT IGNORE statement batching
// ----------------------------------------------------------------------------
$runner->test('INSERT IGNORE statements are properly batched', function () use ($runner) {
    $batcher = new InsertBatcherService(1000);

    // Process INSERT IGNORE statements
    $ignore1 = "INSERT IGNORE INTO users VALUES (1, 'Alice');";
    $ignore2 = "INSERT IGNORE INTO users VALUES (2, 'Bob');";
    $ignore3 = "INSERT IGNORE INTO users VALUES (3, 'Charlie');";

    $result1 = $batcher->process($ignore1);
    $runner->assertEquals([], $result1['queries']);

    $result2 = $batcher->process($ignore2);
    $runner->assertEquals([], $result2['queries']);

    $result3 = $batcher->process($ignore3);
    $runner->assertEquals([], $result3['queries']);

    // Flush and verify batched INSERT IGNORE
    $flushed = $batcher->flush();
    $runner->assertEquals(1, count($flushed['queries']));

    $query = $flushed['queries'][0];
    $runner->assertContains('INSERT IGNORE INTO', $query);
    $runner->assertContains("(1, 'Alice')", $query);
    $runner->assertContains("(2, 'Bob')", $query);
    $runner->assertContains("(3, 'Charlie')", $query);
});

// ----------------------------------------------------------------------------
// Test 4: INSERT IGNORE preserves prefix correctly
// ----------------------------------------------------------------------------
$runner->test('INSERT IGNORE prefix is correctly preserved in batched query', function () use ($runner) {
    $batcher = new InsertBatcherService(1000);

    // Test with various INSERT IGNORE patterns
    $batcher->process("INSERT IGNORE INTO `products` VALUES (1, 'Product A');");
    $batcher->process("INSERT IGNORE INTO `products` VALUES (2, 'Product B');");

    $flushed = $batcher->flush();
    $query = $flushed['queries'][0];

    // Prefix should be "INSERT IGNORE INTO `products` VALUES"
    $runner->assertTrue(
        str_starts_with(strtoupper($query), 'INSERT IGNORE INTO'),
        "Query should start with INSERT IGNORE INTO"
    );
    // Should have both values comma-separated
    $runner->assertContains("), (", $query);
});

// ----------------------------------------------------------------------------
// Test 5: Adaptive batch sizing based on average row size
// ----------------------------------------------------------------------------
$runner->test('Adaptive batch sizing calculates effective batch size from row sizes', function () use ($runner) {
    // maxBatchSize = 100, maxBatchBytes = 500
    // If rows are ~50 bytes each, effective should be min(100, 500/50) = 10
    $batcher = new InsertBatcherService(100, 500);

    // Insert small rows (~20 bytes each)
    // Effective batch size should be min(100, 500/20) = 25
    for ($i = 0; $i < 20; $i++) {
        $batcher->process("INSERT INTO t VALUES ({$i});");
    }

    $stats = $batcher->getStatistics();
    // avg_row_size should be tracked
    $runner->assertArrayHasKey('avg_row_size', $stats);
    $runner->assertGreaterThan(0, $stats['avg_row_size']);

    // effective_batch_size should be calculated
    $runner->assertArrayHasKey('effective_batch_size', $stats);
    $runner->assertGreaterThan(0, $stats['effective_batch_size']);
});

// ----------------------------------------------------------------------------
// Test 6: Batch efficiency metrics accuracy
// ----------------------------------------------------------------------------
$runner->test('Batch efficiency metrics are accurate', function () use ($runner) {
    $batcher = new InsertBatcherService(5);

    // Process 15 INSERTs (should create 3 batched queries)
    for ($i = 1; $i <= 15; $i++) {
        $result = $batcher->process("INSERT INTO t VALUES ({$i}, 'data{$i}');");
        // Every 5th INSERT (except 15th) should trigger flush
        if ($i % 5 === 0 && $i < 15) {
            // Flush triggered by next INSERT, so check for queries
        }
    }

    // Flush remaining
    $batcher->flush();

    $stats = $batcher->getStatistics();

    // Check rows_batched
    $runner->assertArrayHasKey('rows_batched', $stats);
    $runner->assertEquals(15, $stats['rows_batched']);

    // Check queries_executed
    $runner->assertArrayHasKey('queries_executed', $stats);
    $runner->assertEquals(3, $stats['queries_executed']);

    // Check bytes_processed
    $runner->assertArrayHasKey('bytes_processed', $stats);
    $runner->assertGreaterThan(0, $stats['bytes_processed']);

    // Check reduction_ratio (15 rows / 3 queries = 5.0)
    $runner->assertArrayHasKey('reduction_ratio', $stats);
    $runner->assertEquals(5.0, $stats['reduction_ratio']);

    // Check avg_rows_per_batch
    $runner->assertArrayHasKey('avg_rows_per_batch', $stats);
    $runner->assertEquals(5.0, $stats['avg_rows_per_batch']);

    // Check batch_efficiency (5:1 ratio = 80% efficiency)
    // Efficiency = 1 - (1/ratio) = 1 - (1/5) = 0.8
    $runner->assertArrayHasKey('batch_efficiency', $stats);
    $runner->assertGreaterThanOrEqual(0.8, $stats['batch_efficiency']);
});

// ----------------------------------------------------------------------------
// Test 7: INSERT IGNORE mixed with regular INSERT
// ----------------------------------------------------------------------------
$runner->test('INSERT IGNORE and regular INSERT are batched separately', function () use ($runner) {
    $batcher = new InsertBatcherService(1000);

    // Mix of INSERT and INSERT IGNORE
    $batcher->process("INSERT INTO t VALUES (1);");
    $batcher->process("INSERT INTO t VALUES (2);");

    // Different prefix (INSERT IGNORE) should flush previous batch
    $result = $batcher->process("INSERT IGNORE INTO t VALUES (3);");

    // Should have flushed the regular INSERTs
    $runner->assertEquals(1, count($result['queries']));
    $runner->assertContains('INSERT INTO t VALUES', $result['queries'][0]);
    $runner->assertFalse(
        str_contains(strtoupper($result['queries'][0]), 'IGNORE'),
        "Flushed query should not contain IGNORE"
    );

    // Now flush the INSERT IGNORE
    $flushed = $batcher->flush();
    $runner->assertEquals(1, count($flushed['queries']));
    $runner->assertContains('INSERT IGNORE INTO', $flushed['queries'][0]);
});

// Output test results
exit($runner->summary());
