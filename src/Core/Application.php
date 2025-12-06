<?php

declare(strict_types=1);

namespace BigDump\Core;

use BigDump\Config\Config;
use BigDump\Controllers\BigDumpController;
use RuntimeException;
use Throwable;

/**
 * Classe Application - Point d'entrée principal de l'application
 *
 * Cette classe orchestre l'ensemble de l'application MVC,
 * gérant l'initialisation, le routage et l'exécution.
 *
 * @package BigDump\Core
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Application
{
    /**
     * Version de l'application
     */
    public const VERSION = '2.0.0';

    /**
     * Instance de configuration
     * @var Config
     */
    private Config $config;

    /**
     * Instance de requête
     * @var Request
     */
    private Request $request;

    /**
     * Instance de réponse
     * @var Response
     */
    private Response $response;

    /**
     * Instance de router
     * @var Router
     */
    private Router $router;

    /**
     * Instance de vue
     * @var View
     */
    private View $view;

    /**
     * Répertoire racine de l'application
     * @var string
     */
    private string $basePath;

    /**
     * Constructeur
     *
     * @param string $basePath Répertoire racine de l'application
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->initialize();
    }

    /**
     * Initialise l'application
     *
     * @return void
     */
    private function initialize(): void
    {
        // Configuration PHP
        $this->configurePhp();

        // Charger la configuration
        $this->config = new Config($this->basePath . '/config/config.php');

        // Initialiser les composants
        $this->request = new Request();
        $this->response = new Response();
        $this->view = new View($this->basePath . '/src/Views');
        $this->router = new Router();

        // Configurer les routes
        $this->setupRoutes();

        // Passer les données globales à la vue
        $this->view->assign([
            'version' => self::VERSION,
            'scriptUri' => $this->request->getScriptUri(),
            'scriptName' => $this->request->getScriptName(),
            'config' => $this->config,
        ]);
    }

    /**
     * Configure les paramètres PHP
     *
     * @return void
     */
    private function configurePhp(): void
    {
        // Rapport d'erreurs
        error_reporting(E_ALL);

        // Timezone
        if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
            @date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');
        }

        // Temps d'exécution illimité si possible
        @set_time_limit(0);

        // Détection automatique des fins de ligne (dépréciée en PHP 8.1+)
        if (PHP_VERSION_ID < 80100) {
            @ini_set('auto_detect_line_endings', '1');
        }
    }

    /**
     * Configure les routes de l'application
     *
     * @return void
     */
    private function setupRoutes(): void
    {
        $controller = BigDumpController::class;

        $this->router
            ->setDefault($controller, 'index')
            ->register('home', $controller, 'index')
            ->register('upload', $controller, 'upload')
            ->register('delete', $controller, 'delete')
            ->register('import', $controller, 'import')
            ->register('start_import', $controller, 'startImport')
            ->register('ajax_import', $controller, 'ajaxImport');
    }

    /**
     * Exécute l'application
     *
     * @return void
     */
    public function run(): void
    {
        try {
            // Résoudre la route
            $route = $this->router->resolve($this->request);

            // Créer le contrôleur
            $controllerClass = $route['controller'];
            $controller = new $controllerClass(
                $this->config,
                $this->request,
                $this->response,
                $this->view
            );

            // Exécuter l'action
            $action = $route['action'];

            if (!method_exists($controller, $action)) {
                throw new RuntimeException("Action not found: {$action}");
            }

            $controller->$action();

            // Envoyer la réponse
            $this->response->send();

        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Gère les erreurs de l'application
     *
     * @param Throwable $e Exception ou erreur
     * @return void
     */
    private function handleError(Throwable $e): void
    {
        // Log l'erreur si possible
        error_log("BigDump Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        // Afficher l'erreur
        $this->response
            ->setStatusCode(500)
            ->asHtml()
            ->setContent($this->renderError($e))
            ->send();
    }

    /**
     * Rend une page d'erreur
     *
     * @param Throwable $e Exception
     * @return string HTML de l'erreur
     */
    private function renderError(Throwable $e): string
    {
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $e->getLine();

        $debug = '';
        if ($this->config->get('debug', false)) {
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
            $debug = "<pre>{$trace}</pre>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BigDump Error</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .error-box { background: white; border-left: 4px solid #e74c3c; padding: 20px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-top: 0; }
        .details { color: #666; font-size: 14px; }
        pre { background: #f8f8f8; padding: 15px; overflow-x: auto; font-size: 12px; }
        a { color: #3498db; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>Application Error</h1>
        <p><strong>{$message}</strong></p>
        <p class="details">File: {$file} on line {$line}</p>
        {$debug}
        <p><a href="{$this->request->getScriptUri()}">Return to BigDump</a></p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Récupère la configuration
     *
     * @return Config Instance de configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Récupère la requête
     *
     * @return Request Instance de requête
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Récupère la réponse
     *
     * @return Response Instance de réponse
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Récupère la vue
     *
     * @return View Instance de vue
     */
    public function getView(): View
    {
        return $this->view;
    }

    /**
     * Récupère le chemin de base
     *
     * @return string Chemin de base
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
