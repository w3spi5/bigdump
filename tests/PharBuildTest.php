<?php

/**
 * PHAR Build Script Tests
 *
 * Tests for the build script functionality.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();

echo "========================================\n";
echo "PHAR Build Script Test Suite\n";
echo "========================================\n\n";

define('PROJECT_ROOT', dirname(__DIR__));
define('BUILD_SCRIPT', PROJECT_ROOT . '/build/build-phar.php');
define('OUTPUT_DIR', PROJECT_ROOT . '/dist');
define('OUTPUT_FILE', OUTPUT_DIR . '/bigdump.phar');
define('CONFIG_EXAMPLE', OUTPUT_DIR . '/bigdump-config.example.php');

// Test 1: Build script exists
$runner->test('Build script exists', function () use ($runner) {
    $runner->assertFileExists(BUILD_SCRIPT, 'build/build-phar.php should exist');
});

// Test 2: Stubs exist
$runner->test('Entry point stubs exist', function () use ($runner) {
    $webEntry = PROJECT_ROOT . '/build/stubs/web-entry.php';
    $cliEntry = PROJECT_ROOT . '/build/stubs/cli-entry.php';

    $runner->assertFileExists($webEntry, 'web-entry.php should exist');
    $runner->assertFileExists($cliEntry, 'cli-entry.php should exist');
});

// Test 3: Source files exist for collection
$runner->test('Source files exist for collection', function () use ($runner) {
    $srcDir = PROJECT_ROOT . '/src';
    $templatesDir = PROJECT_ROOT . '/templates';

    $runner->assertTrue(is_dir($srcDir), 'src/ directory should exist');
    $runner->assertTrue(is_dir($templatesDir), 'templates/ directory should exist');

    // Check for key files
    $runner->assertFileExists($srcDir . '/Core/Application.php', 'Application.php should exist');
    $runner->assertFileExists($srcDir . '/Core/View.php', 'View.php should exist');
    $runner->assertFileExists($templatesDir . '/layout.php', 'layout.php should exist');
});

// Test 4: Assets exist for collection
$runner->test('Assets exist for inlining', function () use ($runner) {
    $assetsDir = PROJECT_ROOT . '/assets';

    $runner->assertFileExists($assetsDir . '/dist/app.min.css', 'CSS should exist');
    $runner->assertFileExists($assetsDir . '/icons.svg', 'Icons should exist');

    // Check at least one JS file
    $jsFiles = glob($assetsDir . '/dist/*.min.js');
    $runner->assertTrue(count($jsFiles) > 0, 'At least one JS file should exist');
});

// Test 5: Build script can be parsed (syntax check)
$runner->test('Build script has valid PHP syntax', function () use ($runner) {
    $output = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg(BUILD_SCRIPT) . ' 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, 'Build script should have valid syntax: ' . implode("\n", $output));
});

// Test 6: Entry stubs have valid PHP syntax
$runner->test('Entry stubs have valid PHP syntax', function () use ($runner) {
    $webEntry = PROJECT_ROOT . '/build/stubs/web-entry.php';
    $cliEntry = PROJECT_ROOT . '/build/stubs/cli-entry.php';

    $output = [];
    $exitCode = 0;

    exec('php -l ' . escapeshellarg($webEntry) . ' 2>&1', $output, $exitCode);
    $runner->assertEquals(0, $exitCode, 'web-entry.php should have valid syntax');

    exec('php -l ' . escapeshellarg($cliEntry) . ' 2>&1', $output, $exitCode);
    $runner->assertEquals(0, $exitCode, 'cli-entry.php should have valid syntax');
});

// Test 7: Build the PHAR (actual build test)
$runner->test('PHAR builds successfully', function () use ($runner) {
    // Check if phar.readonly is 0 or can be disabled
    $output = [];
    $exitCode = 0;

    // Clean up any existing PHAR
    if (file_exists(OUTPUT_FILE)) {
        unlink(OUTPUT_FILE);
    }
    if (file_exists(CONFIG_EXAMPLE)) {
        unlink(CONFIG_EXAMPLE);
    }

    // Run build script
    $command = 'php -d phar.readonly=0 ' . escapeshellarg(BUILD_SCRIPT) . ' 2>&1';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        // If build fails due to phar.readonly, skip with info
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'phar.readonly') !== false) {
            echo "    (Skipped: phar.readonly cannot be disabled)\n";
            return;
        }
        throw new \RuntimeException("Build failed (exit code {$exitCode}): " . $outputStr);
    }

    $runner->assertEquals(0, $exitCode, 'Build should succeed');
    $runner->assertFileExists(OUTPUT_FILE, 'PHAR file should be created');
});

// Test 8: PHAR contains expected structure
$runner->test('PHAR contains expected files', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    try {
        $phar = new \Phar(OUTPUT_FILE);

        // Check for key files
        $runner->assertTrue(isset($phar['src/Core/Application.php']), 'PHAR should contain Application.php');
        $runner->assertTrue(isset($phar['src/Core/View.php']), 'PHAR should contain View.php');
        $runner->assertTrue(isset($phar['templates/layout.php']), 'PHAR should contain layout.php');
        $runner->assertTrue(isset($phar['build/stubs/web-entry.php']), 'PHAR should contain web-entry.php');
        $runner->assertTrue(isset($phar['build/stubs/cli-entry.php']), 'PHAR should contain cli-entry.php');

    } catch (\Throwable $e) {
        throw new \RuntimeException("Failed to read PHAR: " . $e->getMessage());
    }
});

// Test 9: PHAR contains assets
$runner->test('PHAR contains assets for inlining', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    try {
        $phar = new \Phar(OUTPUT_FILE);

        $runner->assertTrue(isset($phar['assets/dist/app.min.css']), 'PHAR should contain CSS');
        $runner->assertTrue(isset($phar['assets/icons.svg']), 'PHAR should contain icons');

    } catch (\Throwable $e) {
        throw new \RuntimeException("Failed to read PHAR: " . $e->getMessage());
    }
});

// Test 10: Example config generated
$runner->test('Example config file is generated', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    $runner->assertFileExists(CONFIG_EXAMPLE, 'Config example should be generated');

    $content = file_get_contents(CONFIG_EXAMPLE);
    $runner->assertContains('db_server', $content, 'Config should contain db_server');
    $runner->assertContains('db_name', $content, 'Config should contain db_name');
    $runner->assertContains('upload_dir', $content, 'Config should contain upload_dir');
});

// Test 11: PHAR can be executed with --version
$runner->test('PHAR responds to --version flag', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(OUTPUT_FILE) . ' --version 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '--version should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('BigDump', $outputStr, 'Version output should contain BigDump');
});

// Test 12: PHAR responds to --help flag
$runner->test('PHAR responds to --help flag', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    $output = [];
    $exitCode = 0;

    exec('php ' . escapeshellarg(OUTPUT_FILE) . ' --help 2>&1', $output, $exitCode);

    $runner->assertEquals(0, $exitCode, '--help should exit with 0');

    $outputStr = implode("\n", $output);
    $runner->assertContains('Usage:', $outputStr, 'Help output should contain Usage');
    $runner->assertContains('--output', $outputStr, 'Help should mention --output option');
});

// Test 13: PHAR size is reasonable
$runner->test('PHAR size is within expected range', function () use ($runner) {
    if (!file_exists(OUTPUT_FILE)) {
        echo "    (Skipped: PHAR not built)\n";
        return;
    }

    $size = filesize(OUTPUT_FILE);

    // Should be between 50KB and 5MB
    $runner->assertGreaterThan(50 * 1024, $size, 'PHAR should be at least 50KB');
    $runner->assertLessThan(5 * 1024 * 1024, $size, 'PHAR should be less than 5MB');

    echo "    (PHAR size: " . round($size / 1024, 2) . " KB)\n";
});

// Summary
exit($runner->summary());
