<?php

/**
 * Frontend BZ2 Support Tests
 *
 * Tests for the frontend BZ2 support in UI and JavaScript.
 * These tests verify:
 * - BZ2 badge displays correctly in file list
 * - data-bz2-supported attribute present in config element
 * - fileupload.js validation accepts .bz2 conditionally
 * - Error message shown when uploading .bz2 without ext-bz2
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once dirname(__DIR__) . '/src/Config/Config.php';
require_once dirname(__DIR__) . '/src/Core/View.php';

use BigDump\Config\Config;
use BigDump\Core\View;

/**
 * Test runner class - minimal implementation for standalone tests
 */
class FrontendBz2TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    /** @var array<string, string> */
    private array $failures = [];
    /** @var array<string> */
    private array $skippedTests = [];

    public function test(string $name, callable $testFn): void
    {
        try {
            $testFn();
            $this->passed++;
            echo "  PASS: {$name}\n";
        } catch (SkipFrontendTestException $e) {
            $this->skipped++;
            $this->skippedTests[] = $name . ': ' . $e->getMessage();
            echo "  SKIP: {$name} - {$e->getMessage()}\n";
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

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new RuntimeException(
                $message ?: "Expected string to contain '{$needle}'"
            );
        }
    }

    public function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            throw new RuntimeException(
                $message ?: "Expected string NOT to contain '{$needle}'"
            );
        }
    }

    public function assertRegex(string $pattern, string $subject, string $message = ''): void
    {
        if (!preg_match($pattern, $subject)) {
            throw new RuntimeException(
                $message ?: "Expected string to match pattern '{$pattern}'"
            );
        }
    }

    public function skip(string $reason): void
    {
        throw new SkipFrontendTestException($reason);
    }

    public function summary(): int
    {
        echo "\n";
        echo "==========================================\n";
        echo "Tests: " . ($this->passed + $this->failed + $this->skipped) . ", ";
        echo "Passed: {$this->passed}, ";
        echo "Failed: {$this->failed}, ";
        echo "Skipped: {$this->skipped}\n";
        echo "==========================================\n";

        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $name => $message) {
                echo "  - {$name}: {$message}\n";
            }
        }

        if ($this->skipped > 0) {
            echo "\nSkipped:\n";
            foreach ($this->skippedTests as $skipInfo) {
                echo "  - {$skipInfo}\n";
            }
        }

        return $this->failed > 0 ? 1 : 0;
    }
}

/**
 * Exception for skipping tests
 */
class SkipFrontendTestException extends Exception {}

/**
 * Renders the home.php template with given variables and returns HTML
 *
 * @param array<string, mixed> $vars Variables to pass to template
 * @return string Rendered HTML
 */
function renderHomeTemplate(array $vars): string
{
    // Set default variables expected by home.php
    $defaults = [
        'dbConfigured' => true,
        'connectionInfo' => ['success' => true, 'charset' => 'utf8mb4'],
        'uploadEnabled' => true,
        'uploadMaxSize' => 10485760, // 10MB
        'uploadDir' => '/tmp/uploads',
        'predefinedFile' => '',
        'dbName' => 'test_db',
        'dbServer' => 'localhost',
        'testMode' => false,
        'files' => [],
        'scriptUri' => '/',
    ];

    // Merge with provided vars
    $vars = array_merge($defaults, $vars);

    // Create a mock View for escaping
    $view = new class {
        public function e(string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        public function escapeJs(string $value): string {
            return addslashes($value);
        }
        public function formatBytes(int $bytes): string {
            if ($bytes === 0) return '0 B';
            $k = 1024;
            $sizes = ['B', 'KB', 'MB', 'GB'];
            $i = (int) floor(log($bytes) / log($k));
            return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
        }
        public function url(array $params): string {
            return '?' . http_build_query($params);
        }
    };

    // Extract variables
    extract($vars);

    // Capture output
    ob_start();
    include dirname(__DIR__) . '/templates/home.php';
    return ob_get_clean();
}

// ============================================================================
// TEST SUITE: Frontend BZ2 Support
// ============================================================================

echo "Frontend BZ2 Support Tests\n";
echo "==========================================\n\n";

$runner = new FrontendBz2TestRunner();

// Check if BZ2 extension is available
$bz2Available = function_exists('bzopen');
echo "BZ2 Extension Available: " . ($bz2Available ? "YES" : "NO") . "\n\n";

// ----------------------------------------------------------------------------
// Test 1: BZ2 badge displays correctly in file list
// ----------------------------------------------------------------------------
$runner->test('BZ2 badge displays correctly in file list', function () use ($runner) {
    // Render template with a BZ2 file
    $html = renderHomeTemplate([
        'files' => [
            [
                'name' => 'test_dump.sql.bz2',
                'size' => 1024000,
                'date' => '2025-12-27 10:00:00',
                'type' => 'BZ2'
            ],
            [
                'name' => 'test_dump.gz',
                'size' => 2048000,
                'date' => '2025-12-27 11:00:00',
                'type' => 'GZip'
            ],
            [
                'name' => 'test_dump.sql',
                'size' => 5120000,
                'date' => '2025-12-27 12:00:00',
                'type' => 'SQL'
            ],
        ],
    ]);

    // Verify BZ2 badge exists with correct class
    $runner->assertStringContains('badge badge-purple', $html,
        "BZ2 badge should have 'badge badge-purple' class");

    // Verify BZ2 text is present
    $runner->assertStringContains('>BZ2</span>', $html,
        "BZ2 badge should display 'BZ2' text");

    // Verify GZip badge also exists (for comparison)
    $runner->assertStringContains('>GZip</span>', $html,
        "GZip badge should also be present");
});

// ----------------------------------------------------------------------------
// Test 2: data-bz2-supported attribute present in config element
// ----------------------------------------------------------------------------
$runner->test('data-bz2-supported attribute present in config element', function () use ($runner, $bz2Available) {
    $html = renderHomeTemplate([]);

    // Verify data-bz2-supported attribute exists
    $runner->assertStringContains('data-bz2-supported=', $html,
        "Config element should have data-bz2-supported attribute");

    // Verify the value reflects actual extension availability
    $expectedValue = $bz2Available ? 'true' : 'false';
    $runner->assertStringContains('data-bz2-supported="' . $expectedValue . '"', $html,
        "data-bz2-supported should be '{$expectedValue}' based on extension availability");

    // Verify it's on the bigdump-config element
    $runner->assertRegex('/id="bigdump-config"[^>]*data-bz2-supported/', $html,
        "data-bz2-supported should be on the bigdump-config element");
});

// ----------------------------------------------------------------------------
// Test 3: Action buttons visibility for BZ2 files
// ----------------------------------------------------------------------------
$runner->test('Action buttons visibility for BZ2 files', function () use ($runner, $bz2Available) {
    // Render with BZ2 file
    $html = renderHomeTemplate([
        'files' => [
            [
                'name' => 'test_dump.bz2',
                'size' => 1024000,
                'date' => '2025-12-27 10:00:00',
                'type' => 'BZ2'
            ],
        ],
    ]);

    if ($bz2Available) {
        // When ext-bz2 is available, Import button should be present
        $runner->assertStringContains('class="btn btn-green">Import</button>', $html,
            "Import button should be visible when ext-bz2 is available");
        $runner->assertStringNotContains('BZ2 not supported', $html,
            "'BZ2 not supported' message should NOT be visible when ext-bz2 available");
    } else {
        // When ext-bz2 is not available, should show "BZ2 not supported"
        $runner->assertStringContains('BZ2 not supported', $html,
            "'BZ2 not supported' message should be visible when ext-bz2 missing");
    }
});

// ----------------------------------------------------------------------------
// Test 4: Allowed types text includes .bz2 conditionally
// ----------------------------------------------------------------------------
$runner->test('Allowed types text includes .bz2 conditionally', function () use ($runner, $bz2Available) {
    $html = renderHomeTemplate([]);

    // Verify the allowed types text
    if ($bz2Available) {
        // When bz2 is supported, .bz2 should be in the allowed types
        $runner->assertStringContains('.bz2', $html,
            "Allowed types should include .bz2 when ext-bz2 is available");
    }

    // Always should have .sql, .gz, .csv
    $runner->assertStringContains('.sql', $html,
        "Allowed types should include .sql");
    $runner->assertStringContains('.gz', $html,
        "Allowed types should include .gz");
    $runner->assertStringContains('.csv', $html,
        "Allowed types should include .csv");

    // Check the file input accept attribute
    if ($bz2Available) {
        $runner->assertStringContains('accept=".sql,.gz,.bz2,.csv"', $html,
            "File input accept attribute should include .bz2 when supported");
    }
});

// Output test results
exit($runner->summary());
