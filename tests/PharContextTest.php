<?php

/**
 * PharContext Tests
 *
 * Tests for PHAR detection and path resolution utilities.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/src/Core/PharContext.php';

use BigDump\Core\PharContext;

$runner = new TestRunner();

echo "========================================\n";
echo "PharContext Test Suite\n";
echo "========================================\n\n";

// Test 1: isPhar() detection when running from filesystem
$runner->test('isPhar() returns false when running from filesystem', function () use ($runner) {
    PharContext::resetCache();
    $result = PharContext::isPhar();
    $runner->assertFalse($result, 'Expected isPhar() to return false when not in PHAR');
});

// Test 2: getPharRoot() returns correct path for filesystem
$runner->test('getPharRoot() returns project root when not in PHAR', function () use ($runner) {
    PharContext::resetCache();
    $root = PharContext::getPharRoot();

    // Should be the project root (parent of tests directory)
    $expectedRoot = dirname(__DIR__);
    $runner->assertEquals($expectedRoot, $root, 'getPharRoot should return project root');
});

// Test 3: getExternalRoot() returns correct path for filesystem
$runner->test('getExternalRoot() returns project root when not in PHAR', function () use ($runner) {
    PharContext::resetCache();
    $root = PharContext::getExternalRoot();

    // Should be the same as project root when not in PHAR
    $expectedRoot = dirname(__DIR__);
    $runner->assertEquals($expectedRoot, $root, 'getExternalRoot should return project root');
});

// Test 4: getConfigPath() returns correct external config path
$runner->test('getConfigPath() returns correct path for config file', function () use ($runner) {
    PharContext::resetCache();
    $configPath = PharContext::getConfigPath();

    $expectedPath = dirname(__DIR__) . '/bigdump-config.php';
    $runner->assertEquals($expectedPath, $configPath, 'Config path should be in project root');
});

// Test 5: getConfigPath() with custom filename
$runner->test('getConfigPath() accepts custom filename', function () use ($runner) {
    PharContext::resetCache();
    $configPath = PharContext::getConfigPath('custom-config.php');

    $expectedPath = dirname(__DIR__) . '/custom-config.php';
    $runner->assertEquals($expectedPath, $configPath, 'Custom config path should work');
});

// Test 6: getUploadsPath() returns default path
$runner->test('getUploadsPath() returns default uploads path', function () use ($runner) {
    PharContext::resetCache();
    $uploadsPath = PharContext::getUploadsPath(null);

    $expectedPath = dirname(__DIR__) . '/uploads';
    $runner->assertEquals($expectedPath, $uploadsPath, 'Uploads path should default to ./uploads/');
});

// Test 7: getUploadsPath() handles relative path from config
$runner->test('getUploadsPath() resolves relative path from config', function () use ($runner) {
    PharContext::resetCache();
    $uploadsPath = PharContext::getUploadsPath('custom-uploads');

    $expectedPath = dirname(__DIR__) . '/custom-uploads';
    $runner->assertEquals($expectedPath, $uploadsPath, 'Should resolve relative path');
});

// Test 8: getUploadsPath() handles absolute path from config
$runner->test('getUploadsPath() preserves absolute path from config', function () use ($runner) {
    PharContext::resetCache();
    $uploadsPath = PharContext::getUploadsPath('/var/www/uploads');

    $runner->assertEquals('/var/www/uploads', $uploadsPath, 'Should preserve absolute path');
});

// Test 9: getTemplatesPath() returns correct internal path
$runner->test('getTemplatesPath() returns correct templates path', function () use ($runner) {
    PharContext::resetCache();
    $templatesPath = PharContext::getTemplatesPath();

    $expectedPath = dirname(__DIR__) . '/templates';
    $runner->assertEquals($expectedPath, $templatesPath, 'Templates path should be internal');
});

// Test 10: getSrcPath() returns correct internal path
$runner->test('getSrcPath() returns correct src path', function () use ($runner) {
    PharContext::resetCache();
    $srcPath = PharContext::getSrcPath();

    $expectedPath = dirname(__DIR__) . '/src';
    $runner->assertEquals($expectedPath, $srcPath, 'Src path should be internal');
});

// Test 11: getAssetsPath() returns correct internal path
$runner->test('getAssetsPath() returns correct assets path', function () use ($runner) {
    PharContext::resetCache();
    $assetsPath = PharContext::getAssetsPath();

    $expectedPath = dirname(__DIR__) . '/assets';
    $runner->assertEquals($expectedPath, $assetsPath, 'Assets path should be internal');
});

// Test 12: resolveInternalPath() works correctly
$runner->test('resolveInternalPath() resolves path relative to root', function () use ($runner) {
    PharContext::resetCache();
    $resolved = PharContext::resolveInternalPath('src/Core/View.php');

    $expectedPath = dirname(__DIR__) . '/src/Core/View.php';
    $runner->assertEquals($expectedPath, $resolved, 'Should resolve internal path correctly');
});

// Test 13: resolveExternalPath() works correctly
$runner->test('resolveExternalPath() resolves path relative to external root', function () use ($runner) {
    PharContext::resetCache();
    $resolved = PharContext::resolveExternalPath('uploads/dump.sql');

    $expectedPath = dirname(__DIR__) . '/uploads/dump.sql';
    $runner->assertEquals($expectedPath, $resolved, 'Should resolve external path correctly');
});

// Test 14: Path resolution handles leading slashes
$runner->test('resolveInternalPath() strips leading slashes', function () use ($runner) {
    PharContext::resetCache();
    $resolved = PharContext::resolveInternalPath('/templates/layout.php');

    $expectedPath = dirname(__DIR__) . '/templates/layout.php';
    $runner->assertEquals($expectedPath, $resolved, 'Should strip leading slash');
});

// Test 15: resetCache() works correctly
$runner->test('resetCache() clears cached values', function () use ($runner) {
    // Call isPhar to cache the value
    PharContext::isPhar();

    // Reset
    PharContext::resetCache();

    // Should work again without errors
    $result = PharContext::isPhar();
    $runner->assertFalse($result, 'Should work after reset');
});

// Test 16: getUploadsPath() with empty string behaves like null
$runner->test('getUploadsPath() treats empty string as default', function () use ($runner) {
    PharContext::resetCache();
    $uploadsPath = PharContext::getUploadsPath('');

    $expectedPath = dirname(__DIR__) . '/uploads';
    $runner->assertEquals($expectedPath, $uploadsPath, 'Empty string should use default');
});

// Summary
exit($runner->summary());
