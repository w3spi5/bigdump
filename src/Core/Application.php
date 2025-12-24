<?php

declare(strict_types=1);

namespace BigDump\Core;

use BigDump\Config\Config;
use BigDump\Controllers\BigDumpController;
use RuntimeException;
use Throwable;

/**
 * Application Class - Main application entry point.
 *
 * This class orchestrates the entire MVC application,
 * managing initialization, routing and execution.
 *
 * @package BigDump\Core
 * @author  w3spi5
 */
class Application
{
    /**
     * Application version.
     */
    public const VERSION = '2.18';

    /**
     * Configuration instance.
     * @var Config
     */
    private Config $config;

    /**
     * Request instance.
     * @var Request
     */
    private Request $request;

    /**
     * Response instance.
     * @var Response
     */
    private Response $response;

    /**
     * Router instance.
     * @var Router
     */
    private Router $router;

    /**
     * View instance.
     * @var View
     */
    private View $view;

    /**
     * Application root directory.
     * @var string
     */
    private string $basePath;

    /**
     * Constructor.
     *
     * @param string $basePath Application root directory.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->initialize();
    }

    /**
     * Initializes the application.
     *
     * @return void
     */
    private function initialize(): void
    {
        // PHP configuration
        $this->configurePhp();

        // Load configuration
        $this->config = new Config($this->basePath . '/config/config.php');

        // Initialize components
        $this->request = new Request();
        $this->response = new Response();
        $this->view = new View($this->basePath . '/templates');
        $this->router = new Router();

        // Configure routes
        $this->setupRoutes();

        // Pass global data to view
        $this->view->assign([
            'version' => self::VERSION,
            'scriptUri' => $this->request->getScriptUri(),
            'scriptName' => $this->request->getScriptName(),
            'config' => $this->config,
        ]);
    }

    /**
     * Configures PHP settings.
     *
     * @return void
     */
    private function configurePhp(): void
    {
        // Error reporting
        error_reporting(E_ALL);

        // Timezone
        if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
            @date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');
        }

        // Unlimited execution time if possible
        @set_time_limit(0);

        // Automatic line ending detection (deprecated in PHP 8.1+)
        if (PHP_VERSION_ID < 80100) {
            @ini_set('auto_detect_line_endings', '1');
        }
    }

    /**
     * Configures application routes.
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
            ->register('stop_import', $controller, 'stopImport')
            ->register('ajax_import', $controller, 'ajaxImport')
            ->register('sse_import', $controller, 'sseImport')
            ->register('drop_restart', $controller, 'dropRestart')
            ->register('restart_import', $controller, 'restartFromBeginning')
            ->register('preview', $controller, 'preview')
            ->register('history', $controller, 'history')
            ->register('files_list', $controller, 'filesList');
    }

    /**
     * Runs the application.
     *
     * @return void
     */
    public function run(): void
    {
        try {
            // Resolve route
            $route = $this->router->resolve($this->request);

            // Create controller
            $controllerClass = $route['controller'];
            $controller = new $controllerClass(
                $this->config,
                $this->request,
                $this->response,
                $this->view
            );

            // Execute action
            $action = $route['action'];

            if (!method_exists($controller, $action)) {
                throw new RuntimeException("Action not found: {$action}");
            }

            $controller->$action();

            // Send response
            $this->response->send();

        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handles application errors.
     *
     * @param Throwable $e Exception or error.
     * @return void
     */
    private function handleError(Throwable $e): void
    {
        // Log error if possible
        error_log("BigDump Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        // Display error
        $this->response
            ->setStatusCode(500)
            ->asHtml()
            ->setContent($this->renderError($e))
            ->send();
    }

    /**
     * Renders an error page.
     *
     * @param Throwable $e Exception.
     * @return string Error HTML.
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
     * Gets the configuration.
     *
     * @return Config Configuration instance.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Gets the request.
     *
     * @return Request Request instance.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Gets the response.
     *
     * @return Response Response instance.
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Gets the view.
     *
     * @return View View instance.
     */
    public function getView(): View
    {
        return $this->view;
    }

    /**
     * Gets the base path.
     *
     * @return string Base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
