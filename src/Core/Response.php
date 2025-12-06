<?php

declare(strict_types=1);

namespace BigDump\Core;

/**
 * Classe Response - Gère les réponses HTTP
 *
 * Cette classe encapsule la création et l'envoi des réponses HTTP,
 * incluant les headers, le contenu et les réponses AJAX/XML.
 *
 * @package BigDump\Core
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Response
{
    /**
     * Contenu de la réponse
     * @var string
     */
    private string $content = '';

    /**
     * Headers HTTP
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Code de statut HTTP
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Type de contenu
     * @var string
     */
    private string $contentType = 'text/html';

    /**
     * Charset
     * @var string
     */
    private string $charset = 'UTF-8';

    /**
     * Constructeur
     *
     * @param string $content Contenu initial
     * @param int $statusCode Code de statut HTTP
     */
    public function __construct(string $content = '', int $statusCode = 200)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->setNoCacheHeaders();
    }

    /**
     * Configure les headers pour désactiver le cache
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
     * Définit le contenu de la réponse
     *
     * @param string $content Contenu
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Ajoute du contenu à la réponse
     *
     * @param string $content Contenu à ajouter
     * @return self
     */
    public function appendContent(string $content): self
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Récupère le contenu de la réponse
     *
     * @return string Contenu
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Définit un header HTTP
     *
     * @param string $name Nom du header
     * @param string $value Valeur du header
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Définit le code de statut HTTP
     *
     * @param int $statusCode Code de statut
     * @return self
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Définit le type de contenu
     *
     * @param string $contentType Type de contenu
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Configure la réponse pour du XML
     *
     * @return self
     */
    public function asXml(): self
    {
        $this->contentType = 'application/xml';
        return $this;
    }

    /**
     * Configure la réponse pour du JSON
     *
     * @return self
     */
    public function asJson(): self
    {
        $this->contentType = 'application/json';
        return $this;
    }

    /**
     * Configure la réponse pour du HTML
     *
     * @return self
     */
    public function asHtml(): self
    {
        $this->contentType = 'text/html';
        return $this;
    }

    /**
     * Envoie les headers HTTP
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
     * Envoie la réponse complète (headers + contenu)
     *
     * @return void
     */
    public function send(): void
    {
        $this->sendHeaders();
        echo $this->content;
    }

    /**
     * Crée une réponse XML pour AJAX
     *
     * @param array<string, mixed> $data Données à inclure dans le XML
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
     * Crée une réponse JSON
     *
     * @param array<string, mixed> $data Données à encoder
     * @return self
     */
    public function createJsonResponse(array $data): self
    {
        $this->asJson();
        $this->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

        return $this;
    }

    /**
     * Redirige vers une URL
     *
     * @param string $url URL de redirection
     * @param int $statusCode Code de statut (301, 302, 303, 307, 308)
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
     * Crée une réponse d'erreur
     *
     * @param string $message Message d'erreur
     * @param int $statusCode Code de statut HTTP
     * @return self
     */
    public function error(string $message, int $statusCode = 500): self
    {
        $this->statusCode = $statusCode;
        $this->content = $message;

        return $this;
    }
}
