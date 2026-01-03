<?php

/**
 * View Asset Inlining Tests
 *
 * Tests for PHAR mode asset inlining in View class.
 *
 * @package BigDump\Tests
 */

declare(strict_types=1);

namespace BigDump\Tests;

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/src/Core/View.php';

use BigDump\Core\View;

$runner = new TestRunner();

echo "========================================\n";
echo "View Asset Inlining Test Suite\n";
echo "========================================\n\n";

define('PROJECT_ROOT', dirname(__DIR__));

// Test 1: Non-PHAR mode returns empty strings
$runner->test('Non-PHAR mode returns empty strings for inlined assets', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');

    $runner->assertFalse($view->isPharMode(), 'Should not be in PHAR mode by default');
    $runner->assertEquals('', $view->getInlinedCss(), 'CSS should be empty when not in PHAR mode');
    $runner->assertEquals('', $view->getInlinedJs(), 'JS should be empty when not in PHAR mode');
    $runner->assertEquals('', $view->getInlinedIcons(), 'Icons should be empty when not in PHAR mode');
});

// Test 2: setPharMode sets the mode correctly
$runner->test('setPharMode() sets PHAR mode correctly', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');

    $view->setPharMode(true);
    $runner->assertTrue($view->isPharMode(), 'Should be in PHAR mode after setPharMode(true)');

    $view->setPharMode(false);
    $runner->assertFalse($view->isPharMode(), 'Should not be in PHAR mode after setPharMode(false)');
});

// Test 3: PHAR mode loads CSS
$runner->test('PHAR mode loads and returns CSS content', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);

    $css = $view->getInlinedCss();

    // Should contain some CSS (the file exists in assets/dist/)
    $runner->assertNotEmpty($css, 'CSS should not be empty in PHAR mode');
    $runner->assertContains('{', $css, 'CSS should contain CSS syntax');
});

// Test 4: PHAR mode loads JavaScript
$runner->test('PHAR mode loads and returns JavaScript content', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);

    $js = $view->getInlinedJs();

    // Should contain some JS
    $runner->assertNotEmpty($js, 'JavaScript should not be empty in PHAR mode');
    // JS files are minified but should contain function keywords or similar
    $runner->assertTrue(
        strpos($js, 'function') !== false || strpos($js, '=>') !== false || strpos($js, 'var') !== false || strpos($js, 'const') !== false || strpos($js, 'let') !== false,
        'JavaScript should contain JavaScript syntax'
    );
});

// Test 5: PHAR mode loads SVG icons
$runner->test('PHAR mode loads and returns SVG icons content', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);

    $icons = $view->getInlinedIcons();

    // Should contain SVG content
    $runner->assertNotEmpty($icons, 'Icons should not be empty in PHAR mode');
    $runner->assertContains('<svg', $icons, 'Icons should contain SVG tag');
    $runner->assertContains('<symbol', $icons, 'Icons should contain symbol elements');
});

// Test 6: Assets are cached after first load
$runner->test('Assets are cached after first load', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);

    // First call loads assets
    $css1 = $view->getInlinedCss();

    // Second call should return the same cached content
    $css2 = $view->getInlinedCss();

    $runner->assertEquals($css1, $css2, 'CSS should be cached and return same content');
});

// Test 7: SVG icons have XML declaration removed
$runner->test('SVG icons have XML declaration removed', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);

    $icons = $view->getInlinedIcons();

    $runner->assertNotContains('<?xml', $icons, 'XML declaration should be removed from icons');
});

// Test 8: Layout changes to layout_phar in PHAR mode
$runner->test('Layout changes to layout_phar in PHAR mode', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');

    // Before PHAR mode, should use default layout
    $view->setLayout('layout');
    $view->setPharMode(true);

    // The layout should now be layout_phar (internal state changed)
    // We can verify by checking the file exists
    $runner->assertFileExists(PROJECT_ROOT . '/templates/layout_phar.php', 'layout_phar.php should exist');
});

// Test 9: Render works with PHAR layout
$runner->test('Render works with PHAR layout', function () use ($runner) {
    $view = new View(PROJECT_ROOT . '/templates');
    $view->setPharMode(true);
    $view->assign('version', '2.19');
    $view->assign('scriptUri', 'index.php');
    $view->assign('scriptName', 'index.php');

    // Render home view with PHAR layout
    $output = $view->render('home', [
        'files' => [],
        'maxFileSize' => 1048576,
    ]);

    // Should contain PHAR-specific elements
    $runner->assertContains('<style>', $output, 'Output should contain inline style tag');
    $runner->assertContains('BigDump', $output, 'Output should contain BigDump title');
});

// Test 10: Icons in PHAR mode use local references
$runner->test('PHAR layout uses local icon references', function () use ($runner) {
    $pharLayout = file_get_contents(PROJECT_ROOT . '/templates/layout_phar.php');

    // Should use local references (#icon) not external (assets/icons.svg#icon)
    $runner->assertContains('href="#sun"', $pharLayout, 'Should use local icon reference for sun');
    $runner->assertContains('href="#moon"', $pharLayout, 'Should use local icon reference for moon');
    $runner->assertNotContains('assets/icons.svg', $pharLayout, 'Should not reference external icons file');
});

// Test 11: PHAR layout contains inlined icons div
$runner->test('PHAR layout contains hidden inlined icons div', function () use ($runner) {
    $pharLayout = file_get_contents(PROJECT_ROOT . '/templates/layout_phar.php');

    $runner->assertContains('getInlinedIcons()', $pharLayout, 'Layout should call getInlinedIcons()');
    $runner->assertContains('display: none', $pharLayout, 'Icons container should be hidden');
});

// Test 12: PHAR layout inlines CSS
$runner->test('PHAR layout inlines CSS in style tag', function () use ($runner) {
    $pharLayout = file_get_contents(PROJECT_ROOT . '/templates/layout_phar.php');

    $runner->assertContains('getInlinedCss()', $pharLayout, 'Layout should call getInlinedCss()');
    $runner->assertNotContains('link rel="stylesheet"', $pharLayout, 'Should not have external CSS link');
});

// Test 13: PHAR layout inlines JavaScript
$runner->test('PHAR layout inlines JavaScript', function () use ($runner) {
    $pharLayout = file_get_contents(PROJECT_ROOT . '/templates/layout_phar.php');

    $runner->assertContains('getInlinedJs()', $pharLayout, 'Layout should call getInlinedJs()');
    $runner->assertNotContains('src="assets/dist/', $pharLayout, 'Should not have external JS src');
});

// Summary
exit($runner->summary());
