<?php

declare(strict_types=1);

namespace BigDump\Core;

/**
 * Request Class - Encapsulates HTTP request data.
 *
 * This class provides a secure abstraction to access HTTP request data
 * ($_GET, $_POST, $_FILES, $_SERVER).
 *
 * @package BigDump\Core
 * @author  MVC Refactoring
 * @version 2.6
 */
class Request
{
    /**
     * Sanitized GET data.
     * @var array<string, mixed>
     */
    private array $get;

    /**
     * Sanitized POST data.
     * @var array<string, mixed>
     */
    private array $post;

    /**
     * FILES data.
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * SERVER data.
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * Requested action.
     * @var string
     */
    private string $action;

    /**
     * Constructor - Initializes the request with superglobals.
     */
    public function __construct()
    {
        $this->get = $this->sanitizeInput($_GET);
        $this->post = $this->sanitizeInput($_POST);
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->action = $this->determineAction();
    }

    /**
     * Sanitizes input data securely.
     *
     * Unlike the original which removed too many characters,
     * this version preserves valid UTF-8 characters while
     * removing dangerous control characters.
     *
     * @param array<string, mixed> $data Data to sanitize.
     * @return array<string, mixed> Sanitized data.
     */
    private function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitize key
            $cleanKey = $this->sanitizeKey($key);

            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeInput($value);
            } else {
                $sanitized[$cleanKey] = $this->sanitizeValue((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitizes a parameter key.
     *
     * @param string $key Key to sanitize.
     * @return string Sanitized key.
     */
    private function sanitizeKey(string $key): string
    {
        // Keys should only contain alphanumeric characters and underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $key) ?? '';
    }

    /**
     * Sanitizes a parameter value.
     *
     * Preserves valid UTF-8 characters, only removes
     * control characters (except tab, newline, carriage return).
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    private function sanitizeValue(string $value): string
    {
        // Remove control characters except \t, \n, \r
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        // Remove null byte sequences (injection)
        $value = str_replace("\0", '', $value);

        return $value;
    }

    /**
     * Determines the action to execute based on request parameters.
     *
     * @return string Action name.
     */
    private function determineAction(): string
    {
        if ($this->has('uploadbutton')) {
            return 'upload';
        }

        if ($this->has('delete')) {
            return 'delete';
        }

        if ($this->has('start') && $this->has('fn')) {
            if ($this->has('ajaxrequest')) {
                return 'ajax_import';
            }
            return 'import';
        }

        if ($this->has('fn')) {
            return 'start_import';
        }

        return 'home';
    }

    /**
     * Gets a GET value.
     *
     * @param string $key Parameter key.
     * @param mixed $default Default value.
     * @return mixed Parameter value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Gets a POST value.
     *
     * @param string $key Parameter key.
     * @param mixed $default Default value.
     * @return mixed Parameter value.
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Gets a GET or POST value (GET takes priority).
     *
     * @param string $key Parameter key.
     * @param mixed $default Default value.
     * @return mixed Parameter value.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $this->post[$key] ?? $default;
    }

    /**
     * Checks if a parameter exists (GET or POST).
     *
     * @param string $key Parameter key.
     * @return bool True if parameter exists.
     */
    public function has(string $key): bool
    {
        return isset($this->get[$key]) || isset($this->post[$key]);
    }

    /**
     * Gets an integer from parameters.
     *
     * @param string $key Parameter key.
     * @param int $default Default value.
     * @return int Integer value.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return (int) floor((float) $value);
    }

    /**
     * Gets information about an uploaded file.
     *
     * @param string $key File key.
     * @return array<string, mixed>|null File information or null.
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Checks if a file has been uploaded correctly.
     *
     * @param string $key File key.
     * @return bool True if file is valid.
     */
    public function hasValidFile(string $key): bool
    {
        $file = $this->file($key);

        if ($file === null) {
            return false;
        }

        return isset($file['tmp_name'])
            && is_uploaded_file($file['tmp_name'])
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * Gets the requested action.
     *
     * @return string Action name.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Gets a SERVER value.
     *
     * @param string $key Parameter key.
     * @param mixed $default Default value.
     * @return mixed Parameter value.
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Gets the PHP script name.
     *
     * @return string Script name.
     */
    public function getScriptName(): string
    {
        return basename($this->server('SCRIPT_FILENAME', 'index.php'));
    }

    /**
     * Gets the script URI.
     *
     * @return string Script URI.
     */
    public function getScriptUri(): string
    {
        return $this->server('PHP_SELF', '/index.php');
    }

    /**
     * Checks if the request is an AJAX request.
     *
     * @return bool True if AJAX request.
     */
    public function isAjax(): bool
    {
        return $this->has('ajaxrequest')
            || strtolower($this->server('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Gets all GET data.
     *
     * @return array<string, mixed> GET data.
     */
    public function getAllGet(): array
    {
        return $this->get;
    }

    /**
     * Gets all POST data.
     *
     * @return array<string, mixed> POST data.
     */
    public function getAllPost(): array
    {
        return $this->post;
    }
}
