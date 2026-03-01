<?php

/**
 * Request Tests
 *
 * Tests for Request::getScriptUri() handling of index.php and .phar entry points.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/src/Core/Request.php';

use BigDump\Core\Request;
use ReflectionClass;

$runner = new TestRunner();

echo "========================================\n";
echo "Request Test Suite\n";
echo "========================================\n\n";

/**
 * Helper: create a Request with a custom $_SERVER['PHP_SELF'] value.
 */
function makeRequestWithPhpSelf(string $phpSelf): Request
{
    // Backup superglobals
    $backupServer = $_SERVER;
    $backupGet = $_GET;
    $backupPost = $_POST;

    $_GET = [];
    $_POST = [];
    $_SERVER['PHP_SELF'] = $phpSelf;

    $request = new Request();

    // Restore superglobals
    $_SERVER = $backupServer;
    $_GET = $backupGet;
    $_POST = $backupPost;

    return $request;
}

// Test 1: Standard index.php at subdirectory
$runner->test('getScriptUri() strips /index.php from subdirectory path', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/bigdump/index.php');
    $runner->assertEquals('/bigdump', $request->getScriptUri());
});

// Test 2: index.php at root
$runner->test('getScriptUri() returns empty string for /index.php at root', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/index.php');
    $runner->assertEquals('', $request->getScriptUri());
});

// Test 3: PHAR file at subdirectory
$runner->test('getScriptUri() strips /bigdump.phar from subdirectory path', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/tools/bigdump.phar');
    $runner->assertEquals('/tools', $request->getScriptUri());
});

// Test 4: PHAR file at root
$runner->test('getScriptUri() returns empty string for /bigdump.phar at root', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/bigdump.phar');
    $runner->assertEquals('', $request->getScriptUri());
});

// Test 5: PHAR with different name
$runner->test('getScriptUri() strips any .phar filename', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/subdir/my-app-v2.28.phar');
    $runner->assertEquals('/subdir', $request->getScriptUri());
});

// Test 6: Deep nested path with index.php
$runner->test('getScriptUri() handles deep nested paths with index.php', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/a/b/c/index.php');
    $runner->assertEquals('/a/b/c', $request->getScriptUri());
});

// Test 7: Deep nested path with .phar
$runner->test('getScriptUri() handles deep nested paths with .phar', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/a/b/c/dump.phar');
    $runner->assertEquals('/a/b/c', $request->getScriptUri());
});

// Test 8: Path without index.php or .phar is left untouched
$runner->test('getScriptUri() leaves non-matching path untouched', function () use ($runner) {
    $request = makeRequestWithPhpSelf('/bigdump/other.php');
    $runner->assertEquals('/bigdump/other.php', $request->getScriptUri());
});

echo "\n";
exit($runner->summary());
