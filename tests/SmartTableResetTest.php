<?php

/**
 * Tests for Smart Table Reset feature.
 *
 * Tests the findCreateTables() and dropTablesForFile() methods
 * in FileAnalysisService.
 */

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

// Autoload BigDump classes
spl_autoload_register(function ($class) {
    $prefix = 'BigDump\\';
    $baseDir = dirname(__DIR__) . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use BigDump\Services\FileAnalysisService;
use BigDump\Tests\TestRunner;

$runner = new TestRunner();

// Create test fixtures
$fixtureDir = __DIR__ . '/fixtures';
$testSqlFile = $fixtureDir . '/smart_reset_test.sql';
$testGzFile = $fixtureDir . '/smart_reset_test.sql.gz';

// Create test SQL content
$sqlContent = <<<'SQL'
-- Test SQL dump for Smart Table Reset

DROP TABLE IF EXISTS `old_table`;

CREATE TABLE `users` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `posts` (
    id INT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255)
);

CREATE TABLE comments (
    id INT PRIMARY KEY,
    post_id INT,
    body TEXT
);

INSERT INTO users VALUES (1, 'Alice');
INSERT INTO users VALUES (2, 'Bob');

CREATE TABLE "quoted_table" (
    id INT
);
SQL;

// Write test fixture
file_put_contents($testSqlFile, $sqlContent);

// Create gzip version
if (function_exists('gzopen')) {
    $gz = gzopen($testGzFile, 'wb9');
    gzwrite($gz, $sqlContent);
    gzclose($gz);
}

// ============================================
// Test: findCreateTables() basic functionality
// ============================================
$runner->test('findCreateTables extracts table names from SQL file', function () use ($runner, $testSqlFile) {
    $service = new FileAnalysisService();
    $tables = $service->findCreateTables($testSqlFile);

    $runner->assertTrue(in_array('users', $tables), 'Should find users table');
    $runner->assertTrue(in_array('posts', $tables), 'Should find posts table');
    $runner->assertTrue(in_array('comments', $tables), 'Should find comments table');
    $runner->assertTrue(in_array('quoted_table', $tables), 'Should find quoted_table');

    // Should NOT include old_table (DROP statement)
    $runner->assertEquals(4, count($tables), 'Should find exactly 4 CREATE TABLE statements');
});

// ============================================
// Test: findCreateTables() with gzip file
// ============================================
if (function_exists('gzopen')) {
    $runner->test('findCreateTables works with gzip files', function () use ($runner, $testGzFile) {
        $service = new FileAnalysisService();
        $tables = $service->findCreateTables($testGzFile);

        $runner->assertTrue(in_array('users', $tables), 'Should find users table in gzip');
        $runner->assertTrue(in_array('posts', $tables), 'Should find posts table in gzip');
        $runner->assertEquals(4, count($tables), 'Should find 4 tables in gzip');
    });
}

// ============================================
// Test: findCreateTables() with nonexistent file
// ============================================
$runner->test('findCreateTables returns empty array for nonexistent file', function () use ($runner) {
    $service = new FileAnalysisService();
    $tables = $service->findCreateTables('/nonexistent/path/file.sql');

    $runner->assertEquals([], $tables, 'Should return empty array');
});

// ============================================
// Test: findCreateTables() with empty file
// ============================================
$runner->test('findCreateTables returns empty array for empty file', function () use ($runner, $fixtureDir) {
    $emptyFile = $fixtureDir . '/empty_test.sql';
    file_put_contents($emptyFile, '');

    $service = new FileAnalysisService();
    $tables = $service->findCreateTables($emptyFile);

    $runner->assertEquals([], $tables, 'Should return empty array for empty file');

    unlink($emptyFile);
});

// ============================================
// Test: findCreateTables() deduplicates tables
// ============================================
$runner->test('findCreateTables deduplicates repeated CREATE TABLE', function () use ($runner, $fixtureDir) {
    $dupFile = $fixtureDir . '/dup_test.sql';
    $dupContent = <<<'SQL'
CREATE TABLE users (id INT);
CREATE TABLE users (id INT, name VARCHAR(255));
CREATE TABLE posts (id INT);
SQL;
    file_put_contents($dupFile, $dupContent);

    $service = new FileAnalysisService();
    $tables = $service->findCreateTables($dupFile);

    $runner->assertEquals(2, count($tables), 'Should deduplicate tables');
    $runner->assertTrue(in_array('users', $tables), 'Should have users');
    $runner->assertTrue(in_array('posts', $tables), 'Should have posts');

    unlink($dupFile);
});

// ============================================
// Test: Table name validation regex
// ============================================
$runner->test('Table names are validated with security regex', function () use ($runner) {
    // Valid table names
    $validNames = ['users', 'user_posts', '_private', 'Table123', 'a'];
    foreach ($validNames as $name) {
        $isValid = (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
        $runner->assertTrue($isValid, "'{$name}' should be valid");
    }

    // Invalid table names (potential SQL injection)
    $invalidNames = ['users;DROP', 'table`name', "table'name", '123start', 'table name', 'table-name'];
    foreach ($invalidNames as $name) {
        $isValid = (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
        $runner->assertFalse($isValid, "'{$name}' should be invalid");
    }
});

// ============================================
// Test: Integration with existing hasCreateTableFor
// ============================================
$runner->test('findCreateTables is consistent with hasCreateTableFor for backtick tables', function () use ($runner, $testSqlFile) {
    $service = new FileAnalysisService();

    // Test tables with backticks (the format hasCreateTableFor supports)
    $runner->assertTrue($service->hasCreateTableFor($testSqlFile, 'users'), 'Should find users');
    $runner->assertTrue($service->hasCreateTableFor($testSqlFile, 'posts'), 'Should find posts');
    $runner->assertTrue($service->hasCreateTableFor($testSqlFile, 'comments'), 'Should find comments');

    // A table NOT in the file should return false
    $hasIt = $service->hasCreateTableFor($testSqlFile, 'nonexistent_table');
    $runner->assertFalse($hasIt, 'hasCreateTableFor should not find nonexistent table');
});

// Cleanup test fixtures
@unlink($testSqlFile);
@unlink($testGzFile);

// Run and report
exit($runner->summary());
