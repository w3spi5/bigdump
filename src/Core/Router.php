<?php

declare(strict_types=1);

namespace BigDump\Core;

use RuntimeException;

/**
 * Classe Router - Gestionnaire de routes simplifié
 *
 * Cette classe gère le routage des requêtes vers les actions
 * appropriées du contrôleur.
 *
 * @package BigDump\Core
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class Router
{
    /**
     * Routes enregistrées
     * @var array<string, array{controller: string, action: string}>
     */
    private array $routes = [];

    /**
     * Route par défaut
     * @var array{controller: string, action: string}|null
     */
    private ?array $defaultRoute = null;

    /**
     * Enregistre une route
     *
     * @param string $name Nom de la route (correspond à l'action de la requête)
     * @param string $controller Nom de la classe contrôleur
     * @param string $action Nom de la méthode à appeler
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
     * Définit la route par défaut
     *
     * @param string $controller Nom de la classe contrôleur
     * @param string $action Nom de la méthode à appeler
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
     * Résout la route pour une requête donnée
     *
     * @param Request $request Requête HTTP
     * @return array{controller: string, action: string} Route résolue
     * @throws RuntimeException Si aucune route ne correspond
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
     * Vérifie si une route existe
     *
     * @param string $name Nom de la route
     * @return bool True si la route existe
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Récupère toutes les routes enregistrées
     *
     * @return array<string, array{controller: string, action: string}> Routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
