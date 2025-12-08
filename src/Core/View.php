<?php

declare(strict_types=1);

namespace BigDump\Core;

use RuntimeException;

/**
 * View Class - View rendering engine
 *
 * This class handles rendering of PHP templates with support
 * for layout, partials and automatic escaping
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
     * Constructor.
     *
     * @param string $viewsPath Path to views directory.
     */
    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
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
