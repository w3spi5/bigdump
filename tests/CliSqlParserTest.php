<?php

/**
 * CLI SQL Parser Tests
 *
 * Tests for Task Group 3: Standalone SQL Parser Adapter.
 * These tests verify:
 * - Parsing simple single-line query
 * - Parsing multi-line query with string spanning lines
 * - DELIMITER command handling
 * - Comment/empty line filtering
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

use BigDump\Services\CliSqlParser;
use BigDump\Tests\TestRunner;

// ============================================================================
// TEST SUITE: CLI SQL Parser Tests
// ============================================================================

echo "CLI SQL Parser Tests\n";
echo "====================\n\n";

$runner = new TestRunner();

// ----------------------------------------------------------------------------
// Test 1: Parsing simple single-line query
// ----------------------------------------------------------------------------
$runner->test('Parses simple single-line query', function () use ($runner) {
    $parser = new CliSqlParser();

    $result = $parser->parseLine("SELECT * FROM users;\n");

    $runner->assertNotNull($result['query']);
    $runner->assertContains('SELECT * FROM users', $result['query']);
    $runner->assertNull($result['error']);
});

// ----------------------------------------------------------------------------
// Test 2: Parsing multi-line query with string spanning lines
// ----------------------------------------------------------------------------
$runner->test('Parses multi-line query with string spanning lines', function () use ($runner) {
    $parser = new CliSqlParser();

    // First line - opens a string
    $result1 = $parser->parseLine("INSERT INTO t VALUES ('line1\n");
    $runner->assertNull($result1['query'], "Should not return query while in string");

    // Second line - continues the string
    $result2 = $parser->parseLine("line2\n");
    $runner->assertNull($result2['query'], "Should not return query while in string");

    // Third line - closes the string and completes query
    $result3 = $parser->parseLine("line3');\n");
    $runner->assertNotNull($result3['query'], "Should return complete query");
    $runner->assertContains("line1", $result3['query']);
    $runner->assertContains("line2", $result3['query']);
    $runner->assertContains("line3", $result3['query']);
});

// ----------------------------------------------------------------------------
// Test 3: DELIMITER command handling
// ----------------------------------------------------------------------------
$runner->test('Handles DELIMITER command', function () use ($runner) {
    $parser = new CliSqlParser();

    // Change delimiter
    $result = $parser->parseLine("DELIMITER //\n");
    $runner->assertTrue($result['delimiter_changed'], "Should detect delimiter change");
    $runner->assertEquals('//', $parser->getDelimiter());

    // Now query must end with //
    $result2 = $parser->parseLine("SELECT 1;\n");
    $runner->assertNull($result2['query'], "Query should not complete with old delimiter");

    $result3 = $parser->parseLine("SELECT 2//\n");
    // This should complete the accumulated query
    $runner->assertNotNull($result3['query'], "Query should complete with new delimiter");

    // Change back
    $result4 = $parser->parseLine("DELIMITER ;\n");
    $runner->assertTrue($result4['delimiter_changed']);
    $runner->assertEquals(';', $parser->getDelimiter());
});

// ----------------------------------------------------------------------------
// Test 4: Comment/empty line filtering
// ----------------------------------------------------------------------------
$runner->test('Filters comments and empty lines', function () use ($runner) {
    $parser = new CliSqlParser();

    // Empty line
    $result1 = $parser->parseLine("\n");
    $runner->assertNull($result1['query']);

    // Hash comment
    $result2 = $parser->parseLine("# This is a comment\n");
    $runner->assertNull($result2['query']);

    // Double dash comment
    $result3 = $parser->parseLine("-- Another comment\n");
    $runner->assertNull($result3['query']);

    // Actual query should still work
    $result4 = $parser->parseLine("SELECT 1;\n");
    $runner->assertNotNull($result4['query']);
});

// ----------------------------------------------------------------------------
// Test 5: getPendingQuery for incomplete queries at EOF
// ----------------------------------------------------------------------------
$runner->test('Returns pending query at EOF', function () use ($runner) {
    $parser = new CliSqlParser();

    // Start a query without completing it
    $parser->parseLine("SELECT * FROM users\n");
    $parser->parseLine("WHERE id = 1\n");

    // Get pending query (no delimiter at end)
    $pending = $parser->getPendingQuery();
    $runner->assertNotNull($pending);
    $runner->assertContains('SELECT * FROM users', $pending);
    $runner->assertContains('WHERE id = 1', $pending);
});

// ----------------------------------------------------------------------------
// Test 6: Double-quoted strings
// ----------------------------------------------------------------------------
$runner->test('Handles double-quoted strings', function () use ($runner) {
    $parser = new CliSqlParser();

    // Query with double-quoted string
    $result = $parser->parseLine("SELECT \"column\" FROM \"table\";\n");
    $runner->assertNotNull($result['query']);
    $runner->assertContains('"column"', $result['query']);
});

// Output test results
exit($runner->summary());
