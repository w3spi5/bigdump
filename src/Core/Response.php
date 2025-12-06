<?php

declare(strict_types=1);

namespace BigDump\Core;

/**
 * Response Class - Manages HTTP responses.
 *
 * This class encapsulates creation and sending of HTTP responses,
 * including headers, content and AJAX/XML responses.
 *
 * @package BigDump\Core
 * @author  MVC Refactoring
 * @version 2.5
 */
class Response
{
    /**
     * Response content.
     * @var string
     */
    private string $content = '';

    /**
     * HTTP headers.
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * HTTP status code.
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Content type.
     * @var string
     */
    private string $contentType = 'text/html';

    /**
     * Charset.
     * @var string
     */
    private string $charset = 'UTF-8';

    /**
     * Constructor.
     *
     * @param string $content Initial content.
     * @param int $statusCode HTTP status code.
     */
    public function __construct(string $content = '', int $statusCode = 200)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->setNoCacheHeaders();
    }

    /**
     * Configures headers to disable cache.
     *
     * @return self
     */
    public function setNoCacheHeaders(): self
    {
        $this->headers['Expires'] = 'Mon, 1 Dec 2003 01:00:00 GMT';
        $this->headers['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
        $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $this->headers['Pragma'] = 'no-cache';

        return $this;
    }

    /**
     * Sets the response content.
     *
     * @param string $content Content.
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Appends content to the response.
     *
     * @param string $content Content to append.
     * @return self
     */
    public function appendContent(string $content): self
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Gets the response content.
     *
     * @return string Content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Sets an HTTP header.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Sets the HTTP status code.
     *
     * @param int $statusCode Status code.
     * @return self
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Sets the content type.
     *
     * @param string $contentType Content type.
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Configures the response for XML.
     *
     * @return self
     */
    public function asXml(): self
    {
        $this->contentType = 'application/xml';
        return $this;
    }

    /**
     * Configures the response for JSON.
     *
     * @return self
     */
    public function asJson(): self
    {
        $this->contentType = 'application/json';
        return $this;
    }

    /**
     * Configures the response for HTML.
     *
     * @return self
     */
    public function asHtml(): self
    {
        $this->contentType = 'text/html';
        return $this;
    }

    /**
     * Sends HTTP headers.
     *
     * @return self
     */
    public function sendHeaders(): self
    {
        if (headers_sent()) {
            return $this;
        }

        http_response_code($this->statusCode);

        header("Content-Type: {$this->contentType}; charset={$this->charset}");

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        return $this;
    }

    /**
     * Sends the complete response (headers + content).
     *
     * @return void
     */
    public function send(): void
    {
        $this->sendHeaders();
        echo $this->content;
    }

    /**
     * Creates an XML response for AJAX.
     *
     * @param array<string, mixed> $data Data to include in XML.
     * @return self
     */
    public function createXmlResponse(array $data): self
    {
        $this->asXml();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<root>';

        foreach ($data as $key => $value) {
            $escapedValue = htmlspecialchars((string) $value, ENT_XML1, 'UTF-8');
            $xml .= "<{$key}>{$escapedValue}</{$key}>";
        }

        $xml .= '</root>';

        $this->content = $xml;

        return $this;
    }

    /**
     * Creates a JSON response.
     *
     * @param array<string, mixed> $data Data to encode.
     * @return self
     */
    public function createJsonResponse(array $data): self
    {
        $this->asJson();
        $this->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

        return $this;
    }

    /**
     * Redirects to a URL.
     *
     * @param string $url Redirect URL.
     * @param int $statusCode Status code (301, 302, 303, 307, 308).
     * @return never
     */
    public function redirect(string $url, int $statusCode = 302): never
    {
        $this->statusCode = $statusCode;
        $this->setHeader('Location', $url);
        $this->sendHeaders();
        exit;
    }

    /**
     * Creates an error response.
     *
     * @param string $message Error message.
     * @param int $statusCode HTTP status code.
     * @return self
     */
    public function error(string $message, int $statusCode = 500): self
    {
        $this->statusCode = $statusCode;
        $this->content = $message;

        return $this;
    }
}
