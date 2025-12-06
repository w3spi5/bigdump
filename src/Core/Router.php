<?php

declare(strict_types=1);

namespace BigDump\Core;

use RuntimeException;

/**
 * Router Class - Simplified route handler.
 *
 * This class manages routing of requests to appropriate
 * controller actions.
 *
 * @package BigDump\Core
 * @author  MVC Refactoring
 * @version 2.3
 */
class Router
{
    /**
     * Registered routes.
     * @var array<string, array{controller: string, action: string}>
     */
    private array $routes = [];

    /**
     * Default route.
     * @var array{controller: string, action: string}|null
     */
    private ?array $defaultRoute = null;

    /**
     * Registers a route.
     *
     * @param string $name Route name (corresponds to request action).
     * @param string $controller Controller class name.
     * @param string $action Method name to call.
     * @return self
     */
    public function register(string $name, string $controller, string $action): self
    {
        $this->routes[$name] = [
            'controller' => $controller,
            'action' => $action,
        ];

        return $this;
    }

    /**
     * Sets the default route.
     *
     * @param string $controller Controller class name.
     * @param string $action Method name to call.
     * @return self
     */
    public function setDefault(string $controller, string $action): self
    {
        $this->defaultRoute = [
            'controller' => $controller,
            'action' => $action,
        ];

        return $this;
    }

    /**
     * Resolves the route for a given request.
     *
     * @param Request $request HTTP request.
     * @return array{controller: string, action: string} Resolved route.
     * @throws RuntimeException If no matching route found.
     */
    public function resolve(Request $request): array
    {
        $actionName = $request->getAction();

        if (isset($this->routes[$actionName])) {
            return $this->routes[$actionName];
        }

        if ($this->defaultRoute !== null) {
            return $this->defaultRoute;
        }

        throw new RuntimeException("No route found for action: {$actionName}");
    }

    /**
     * Checks if a route exists.
     *
     * @param string $name Route name.
     * @return bool True if route exists.
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Gets all registered routes.
     *
     * @return array<string, array{controller: string, action: string}> Routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
