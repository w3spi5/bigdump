<?php

/**
 * SQL Parser Optimization Tests
 *
 * Tests for the SqlParser quote analysis skip optimization (v2.25+):
 * - Skipping analyzeQuotes for comments/empty lines when not in string
 * - Multi-line string spanning comment-like content
 * - Proper handling of edge cases
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Models/SqlParser.php';

use BigDump\Config\Config;
use BigDump\Models\SqlParser;

/**
 * Simple test runner for standalone tests
 */
class SqlParserOptTestRunner
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

    public function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            throw new RuntimeException($message ?: "Expected null, got " . var_export($value, true));
        }
    }

    public function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            throw new RuntimeException($message ?: "Expected non-null value, got null");
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

/**
 * Creates a temporary config file
 */
function createTestConfig(): string
{
    $tempFile = sys_get_temp_dir() . '/bigdump_sqlparser_test_' . uniqid() . '.php';
    $config = [
        'db_name' => 'test',
        'db_username' => 'test',
        'comment_markers' => ['#', '-- '],
    ];
    file_put_contents($tempFile, "<?php\nreturn " . var_export($config, true) . ";\n");
    return $tempFile;
}

/**
 * Cleanup temporary config file
 */
function cleanupTestConfig(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

// ============================================================================
// TEST SUITE: SQL Parser Quote Analysis Skip Optimization
// ============================================================================

echo "SQL Parser Optimization Tests (v2.25+)\n";
echo "======================================\n\n";

$runner = new SqlParserOptTestRunner();

// ----------------------------------------------------------------------------
// Test 1: Comment lines are skipped (early return)
// ----------------------------------------------------------------------------
$runner->test('Comment lines with # are skipped', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("# This is a comment\n");

        $runner->assertNull($result['query']);
        $runner->assertNull($result['error']);
        $runner->assertFalse($result['delimiter_changed']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 2: Comment lines with -- are skipped
// ----------------------------------------------------------------------------
$runner->test('Comment lines with -- are skipped', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("-- This is a SQL comment\n");

        $runner->assertNull($result['query']);
        $runner->assertNull($result['error']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 3: Empty lines are skipped
// ----------------------------------------------------------------------------
$runner->test('Empty lines are skipped', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("\n");

        $runner->assertNull($result['query']);
        $runner->assertNull($result['error']);

        // Whitespace only
        $result2 = $parser->parseLine("   \n");
        $runner->assertNull($result2['query']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 4: Multi-line string spanning comment-like content is preserved
// ----------------------------------------------------------------------------
$runner->test('Multi-line string with comment-like content is preserved', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Start of INSERT with multi-line string
        $result1 = $parser->parseLine("INSERT INTO test VALUES ('line1\n");
        $runner->assertNull($result1['query']); // Not complete yet
        $runner->assertTrue($parser->isInString());

        // This line looks like a comment but is inside a string
        $result2 = $parser->parseLine("# This is NOT a comment - it is inside a string\n");
        $runner->assertNull($result2['query']); // Still not complete
        $runner->assertTrue($parser->isInString()); // Still in string

        // Another line that looks like a comment
        $result3 = $parser->parseLine("-- Also not a comment\n");
        $runner->assertNull($result3['query']);
        $runner->assertTrue($parser->isInString());

        // Close the string and complete the query
        $result4 = $parser->parseLine("end of string');\n");
        $runner->assertNotNull($result4['query']);
        $runner->assertFalse($parser->isInString());

        // Verify the query contains the "comment-like" content
        $query = $result4['query'];
        $runner->assertTrue(strpos($query, '# This is NOT a comment') !== false);
        $runner->assertTrue(strpos($query, '-- Also not a comment') !== false);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 5: Regular SQL is parsed correctly after comment
// ----------------------------------------------------------------------------
$runner->test('Regular SQL after comments is parsed correctly', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Comment line
        $parser->parseLine("# Initial comment\n");

        // Empty line
        $parser->parseLine("\n");

        // SQL query
        $result = $parser->parseLine("SELECT 1;\n");

        $runner->assertNotNull($result['query']);
        $runner->assertEquals('SELECT 1', $result['query']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 6: DELIMITER command is detected outside string
// ----------------------------------------------------------------------------
$runner->test('DELIMITER command detected outside string', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("DELIMITER //\n");

        $runner->assertTrue($result['delimiter_changed']);
        $runner->assertEquals('//', $parser->getDelimiter());
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 7: DELIMITER inside string is NOT treated as command
// ----------------------------------------------------------------------------
$runner->test('DELIMITER inside string is NOT a command', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Start a multi-line string
        $parser->parseLine("INSERT INTO test VALUES ('text\n");
        $runner->assertTrue($parser->isInString());

        // DELIMITER inside string should NOT change delimiter
        $result = $parser->parseLine("DELIMITER //\n");
        $runner->assertFalse($result['delimiter_changed']);
        $runner->assertEquals(';', $parser->getDelimiter()); // Unchanged

        $runner->assertTrue($parser->isInString());

        // Close the string
        $parser->parseLine("end');\n");
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 8: Performance - many comment lines processed efficiently
// ----------------------------------------------------------------------------
$runner->test('Many comment lines are processed efficiently', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $startTime = microtime(true);

        // Process 10000 comment lines
        for ($i = 0; $i < 10000; $i++) {
            $parser->parseLine("# Comment line {$i}\n");
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete in under 1 second (typically < 0.1s)
        $runner->assertTrue(
            $duration < 1.0,
            "Processing 10000 comment lines took {$duration}s (should be < 1s)"
        );
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 9: Empty string content is handled correctly
// ----------------------------------------------------------------------------
$runner->test('Empty string content is handled correctly', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("INSERT INTO test VALUES ('');\n");

        $runner->assertNotNull($result['query']);
        $runner->assertEquals("INSERT INTO test VALUES ('')", $result['query']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 10: String with only whitespace
// ----------------------------------------------------------------------------
$runner->test('String with only whitespace is preserved', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        $result = $parser->parseLine("INSERT INTO test VALUES ('   ');\n");

        $runner->assertNotNull($result['query']);
        $runner->assertEquals("INSERT INTO test VALUES ('   ')", $result['query']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 11: Mixed content - comments, empty lines, and SQL
// ----------------------------------------------------------------------------
$runner->test('Mixed content with comments, empty lines, and SQL', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Comments and empty lines should be skipped
        $parser->parseLine("# Header comment\n");
        $parser->parseLine("\n");
        $parser->parseLine("-- Another comment\n");
        $parser->parseLine("\n");

        // This SQL should parse correctly
        $result1 = $parser->parseLine("CREATE TABLE test (id INT);\n");
        $runner->assertNotNull($result1['query']);
        $runner->assertEquals("CREATE TABLE test (id INT)", $result1['query']);

        // More comments
        $parser->parseLine("# Middle comment\n");

        // Another SQL
        $result2 = $parser->parseLine("INSERT INTO test VALUES (1);\n");
        $runner->assertNotNull($result2['query']);
        $runner->assertEquals("INSERT INTO test VALUES (1)", $result2['query']);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 12: Double quotes string with comment-like content
// ----------------------------------------------------------------------------
$runner->test('Double quotes string with comment-like content', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Multi-line with double quotes
        $parser->parseLine('INSERT INTO test VALUES ("line1' . "\n");
        $runner->assertTrue($parser->isInString());

        // Comment-like content inside double-quoted string
        $parser->parseLine("# Not a comment\n");
        $runner->assertTrue($parser->isInString());

        // Close and complete
        $result = $parser->parseLine('end")' . ";\n");
        $runner->assertNotNull($result['query']);
        $runner->assertTrue(strpos($result['query'], '# Not a comment') !== false);
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 13: SQL comment lines without space after -- are filtered (v2.26 fix)
// ----------------------------------------------------------------------------
$runner->test('SQL comment lines without space after -- are filtered', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // phpMyAdmin style separators (-- without trailing space)
        $result1 = $parser->parseLine("--\n");
        $runner->assertNull($result1['query'], "Line with just -- should be filtered");

        $result2 = $parser->parseLine("-- Index pour la table\n");
        $runner->assertNull($result2['query'], "Line with -- and space should be filtered");

        $result3 = $parser->parseLine("--\n");
        $runner->assertNull($result3['query'], "Another -- line should be filtered");

        // The actual DDL statement
        $result4 = $parser->parseLine("ALTER TABLE `test` ADD PRIMARY KEY (`id`);\n");
        $runner->assertNotNull($result4['query'], "ALTER TABLE should return a query");
        $runner->assertTrue(
            str_starts_with(trim($result4['query']), 'ALTER TABLE'),
            "Query should start with ALTER TABLE, not --"
        );
        $runner->assertFalse(
            str_contains($result4['query'], '--'),
            "Query should not contain comment markers"
        );
    } finally {
        cleanupTestConfig($configFile);
    }
});

// ----------------------------------------------------------------------------
// Test 14: Multi-line ALTER TABLE with phpMyAdmin comments parses correctly
// ----------------------------------------------------------------------------
$runner->test('Multi-line ALTER TABLE with phpMyAdmin comments parses correctly', function () use ($runner) {
    $configFile = createTestConfig();

    try {
        $config = new Config($configFile);
        $parser = new SqlParser($config);

        // Simulate phpMyAdmin dump format
        $lines = [
            "--\n",
            "-- Index pour les tables déchargées\n",
            "--\n",
            "\n",
            "--\n",
            "-- Index pour la table `wespy_diffusion`\n",
            "--\n",
            "ALTER TABLE `wespy_diffusion`\n",
            "  ADD PRIMARY KEY (`id_diffusion`),\n",
            "  ADD KEY `titre_id` (`titre_id`),\n",
            "  ADD KEY `station_id` (`station_id`);\n",
        ];

        $query = null;
        foreach ($lines as $line) {
            $result = $parser->parseLine($line);
            if ($result['query'] !== null) {
                $query = $result['query'];
            }
        }

        $runner->assertNotNull($query, "Should have parsed ALTER TABLE query");
        $runner->assertTrue(
            str_starts_with(trim($query), 'ALTER TABLE'),
            "Query should start with ALTER TABLE"
        );
        $runner->assertTrue(
            str_contains($query, 'ADD PRIMARY KEY'),
            "Query should contain ADD PRIMARY KEY"
        );
        $runner->assertTrue(
            str_contains($query, 'ADD KEY `titre_id`'),
            "Query should contain ADD KEY titre_id"
        );
    } finally {
        cleanupTestConfig($configFile);
    }
});

// Output test results
exit($runner->summary());
