<?php

declare(strict_types=1);

namespace BigDump\Core;

use RuntimeException;

/**
 * View Class - View rendering engine
 *
 * This class handles rendering of PHP templates with support
 * for layout, partials and automatic escaping.
 *
 * Supports PHAR mode with inlined CSS, JavaScript, and SVG assets.
 *
 * @package BigDump\Core
 * @author  w3spi5
 */
class View
{
    /**
     * Views directory.
     * @var string
     */
    private string $viewsPath;

    /**
     * Default layout.
     * @var string|null
     */
    private ?string $layout = 'layout';

    /**
     * Data passed to the view.
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Main section content.
     * @var string
     */
    private string $sectionContent = '';

    /**
     * Whether running in PHAR mode.
     * @var bool
     */
    private bool $isPharMode = false;

    /**
     * Cached inlined CSS content.
     * @var string|null
     */
    private ?string $inlinedCss = null;

    /**
     * Cached inlined JavaScript content.
     * @var string|null
     */
    private ?string $inlinedJs = null;

    /**
     * Cached inlined SVG icons content.
     * @var string|null
     */
    private ?string $inlinedIcons = null;

    /**
     * Constructor.
     *
     * @param string $viewsPath Path to views directory.
     */
    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
    }

    /**
     * Sets PHAR mode for asset inlining.
     *
     * @param bool $mode True to enable PHAR mode.
     * @return self
     */
    public function setPharMode(bool $mode): self
    {
        $this->isPharMode = $mode;

        // Use PHAR layout when in PHAR mode
        if ($mode && $this->layout === 'layout') {
            $this->layout = 'layout_phar';
        }

        return $this;
    }

    /**
     * Checks if running in PHAR mode.
     *
     * @return bool True if in PHAR mode.
     */
    public function isPharMode(): bool
    {
        return $this->isPharMode;
    }

    /**
     * Sets the layout to use.
     *
     * @param string|null $layout Layout name (null to disable).
     * @return self
     */
    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Assigns data to the view.
     *
     * @param string|array<string, mixed> $key Key or associative array.
     * @param mixed $value Value (ignored if $key is an array).
     * @return self
     */
    public function assign(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Gets an assigned data value.
     *
     * @param string $key Key.
     * @param mixed $default Default value.
     * @return mixed Value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Renders a view and returns HTML.
     *
     * @param string $view View name (without extension).
     * @param array<string, mixed> $data Additional data.
     * @return string Rendered HTML.
     * @throws RuntimeException If view does not exist.
     */
    public function render(string $view, array $data = []): string
    {
        $viewFile = $this->viewsPath . '/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        // Merge data
        $allData = array_merge($this->data, $data);

        // Capture view content
        $content = $this->capture($viewFile, $allData);

        // If layout is defined, use it
        if ($this->layout !== null) {
            $layoutFile = $this->viewsPath . '/' . $this->layout . '.php';

            // Fall back to regular layout if PHAR layout doesn't exist
            if (!file_exists($layoutFile) && $this->layout === 'layout_phar') {
                $layoutFile = $this->viewsPath . '/layout.php';
            }

            if (file_exists($layoutFile)) {
                $this->sectionContent = $content;
                $allData['content'] = $content;
                $content = $this->capture($layoutFile, $allData);
            }
        }

        return $content;
    }

    /**
     * Captures output from a PHP file.
     *
     * @param string $file File path.
     * @param array<string, mixed> $data Data to extract.
     * @return string Captured output.
     */
    private function capture(string $file, array $data): string
    {
        // Extract data as local variables
        extract($data, EXTR_SKIP);

        // $view variable accessible in templates
        $view = $this;

        ob_start();

        try {
            include $file;
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Renders a partial (partial view).
     *
     * @param string $partial Partial name (prefixed with partials/).
     * @param array<string, mixed> $data Data for partial.
     * @return string Rendered HTML.
     */
    public function partial(string $partial, array $data = []): string
    {
        $partialFile = $this->viewsPath . '/partials/' . $partial . '.php';

        if (!file_exists($partialFile)) {
            return "<!-- Partial not found: {$partial} -->";
        }

        return $this->capture($partialFile, array_merge($this->data, $data));
    }

    /**
     * Gets inlined CSS content for PHAR mode.
     *
     * @return string CSS content or empty string if not in PHAR mode.
     */
    public function getInlinedCss(): string
    {
        if (!$this->isPharMode) {
            return '';
        }

        if ($this->inlinedCss === null) {
            $this->loadInlinedAssets();
        }

        return $this->inlinedCss ?? '';
    }

    /**
     * Gets inlined JavaScript content for PHAR mode.
     *
     * @return string JavaScript content or empty string if not in PHAR mode.
     */
    public function getInlinedJs(): string
    {
        if (!$this->isPharMode) {
            return '';
        }

        if ($this->inlinedJs === null) {
            $this->loadInlinedAssets();
        }

        return $this->inlinedJs ?? '';
    }

    /**
     * Gets inlined SVG icons content for PHAR mode.
     *
     * @return string SVG content or empty string if not in PHAR mode.
     */
    public function getInlinedIcons(): string
    {
        if (!$this->isPharMode) {
            return '';
        }

        if ($this->inlinedIcons === null) {
            $this->loadInlinedAssets();
        }

        return $this->inlinedIcons ?? '';
    }

    /**
     * Loads and caches all inlined assets from PHAR.
     *
     * @return void
     */
    private function loadInlinedAssets(): void
    {
        // Determine PHAR root (parent of templates directory)
        $pharRoot = dirname($this->viewsPath);

        // Load CSS
        $cssFile = $pharRoot . '/assets/dist/app.min.css';
        if (file_exists($cssFile)) {
            $this->inlinedCss = file_get_contents($cssFile);
        } else {
            $this->inlinedCss = '';
        }

        // Load and concatenate all JS files
        $jsContent = [];
        $jsOrder = [
            'bigdump.min.js',
            'preview.min.js',
            'history.min.js',
            'modal.min.js',
            'filepolling.min.js',
            'fileupload.min.js',
        ];

        foreach ($jsOrder as $jsFile) {
            $jsPath = $pharRoot . '/assets/dist/' . $jsFile;
            if (file_exists($jsPath)) {
                $jsContent[] = file_get_contents($jsPath);
            }
        }

        $this->inlinedJs = implode("\n", $jsContent);

        // Load SVG icons
        $iconsFile = $pharRoot . '/assets/icons.svg';
        if (file_exists($iconsFile)) {
            $icons = file_get_contents($iconsFile);
            // Remove XML declaration if present
            $icons = preg_replace('/<\?xml[^?]*\?>/', '', $icons);
            // Remove comments
            $icons = preg_replace('/<!--.*?-->/s', '', $icons);
            $this->inlinedIcons = trim($icons);
        } else {
            $this->inlinedIcons = '';
        }
    }

    /**
     * Escapes a string for HTML display.
     *
     * @param mixed $value Value to escape.
     * @return string Escaped value.
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Short alias for escape().
     *
     * @param mixed $value Value to escape.
     * @return string Escaped value.
     */
    public function e(mixed $value): string
    {
        return $this->escape($value);
    }

    /**
     * Escapes a string for safe use in JavaScript.
     *
     * Useful for onclick attributes and other inline JavaScript contexts.
     * Also escapes Unicode line terminators (U+2028, U+2029) which are
     * valid in JSON but treated as line terminators in JavaScript.
     *
     * @param mixed $value Value to escape.
     * @return string Value escaped for JavaScript.
     */
    public function escapeJs(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;

        // Escape control characters, quotes and backslashes
        $escaped = addcslashes($string, "\0..\37\"'\\");

        // Escape Unicode line terminators (U+2028, U+2029)
        // These are valid in JSON but treated as line terminators in JavaScript
        $escaped = str_replace(["\u{2028}", "\u{2029}"], ['\\u2028', '\\u2029'], $escaped);

        // Escape sequences that could break out of script context
        $escaped = str_replace(['</', '<!--', '-->'], ['<\\/', '<\\!--', '--\\>'], $escaped);

        return $escaped;
    }

    /**
     * Gets the main section content (for layout).
     *
     * @return string Content.
     */
    public function content(): string
    {
        return $this->sectionContent;
    }

    /**
     * Generates a URL with parameters.
     *
     * @param array<string, mixed> $params URL parameters.
     * @return string Generated URL.
     */
    public function url(array $params = []): string
    {
        if (empty($params)) {
            return $this->get('scriptUri', 'index.php');
        }

        $query = http_build_query($params);
        return $this->get('scriptUri', 'index.php') . '?' . $query;
    }

    /**
     * Formats a number of bytes in readable format.
     *
     * @param int|float $bytes Number of bytes.
     * @param int $precision Decimal precision.
     * @return string Formatted size.
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[(int) $pow];
    }

    /**
     * Checks if a variable is defined and not empty.
     *
     * @param string $key Variable key.
     * @return bool True if defined and not empty.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== '';
    }
}
