<?php

declare(strict_types=1);

namespace BigDump\Core;

use RuntimeException;

/**
 * Classe View - Moteur de rendu des vues
 *
 * Cette classe gère le rendu des templates PHP avec support
 * du layout, des partials et de l'échappement automatique.
 *
 * @package BigDump\Core
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class View
{
    /**
     * Répertoire des vues
     * @var string
     */
    private string $viewsPath;

    /**
     * Layout par défaut
     * @var string|null
     */
    private ?string $layout = 'layout';

    /**
     * Données passées à la vue
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Contenu de la section principale
     * @var string
     */
    private string $sectionContent = '';

    /**
     * Constructeur
     *
     * @param string $viewsPath Chemin vers le répertoire des vues
     */
    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
    }

    /**
     * Définit le layout à utiliser
     *
     * @param string|null $layout Nom du layout (null pour désactiver)
     * @return self
     */
    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Assigne des données à la vue
     *
     * @param string|array<string, mixed> $key Clé ou tableau associatif
     * @param mixed $value Valeur (ignorée si $key est un tableau)
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
     * Récupère une donnée assignée
     *
     * @param string $key Clé
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Rend une vue et retourne le HTML
     *
     * @param string $view Nom de la vue (sans extension)
     * @param array<string, mixed> $data Données supplémentaires
     * @return string HTML rendu
     * @throws RuntimeException Si la vue n'existe pas
     */
    public function render(string $view, array $data = []): string
    {
        $viewFile = $this->viewsPath . '/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        // Fusionner les données
        $allData = array_merge($this->data, $data);

        // Capturer le contenu de la vue
        $content = $this->capture($viewFile, $allData);

        // Si un layout est défini, l'utiliser
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
     * Capture la sortie d'un fichier PHP
     *
     * @param string $file Chemin du fichier
     * @param array<string, mixed> $data Données à extraire
     * @return string Sortie capturée
     */
    private function capture(string $file, array $data): string
    {
        // Extraire les données comme variables locales
        extract($data, EXTR_SKIP);

        // Variable $view accessible dans les templates
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
     * Rend un partial (vue partielle)
     *
     * @param string $partial Nom du partial (préfixé avec partials/)
     * @param array<string, mixed> $data Données pour le partial
     * @return string HTML rendu
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
     * Échappe une chaîne pour l'affichage HTML
     *
     * @param mixed $value Valeur à échapper
     * @return string Valeur échappée
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Alias court pour escape()
     *
     * @param mixed $value Valeur à échapper
     * @return string Valeur échappée
     */
    public function e(mixed $value): string
    {
        return $this->escape($value);
    }

    /**
     * Échappe une chaîne pour une utilisation sûre dans du JavaScript
     *
     * Utile pour les attributs onclick et autres contextes JavaScript inline.
     *
     * @param mixed $value Valeur à échapper
     * @return string Valeur échappée pour JavaScript
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
     * Récupère le contenu de la section principale (pour le layout)
     *
     * @return string Contenu
     */
    public function content(): string
    {
        return $this->sectionContent;
    }

    /**
     * Génère une URL avec des paramètres
     *
     * @param array<string, mixed> $params Paramètres de l'URL
     * @return string URL générée
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
     * Formate un nombre d'octets en format lisible
     *
     * @param int|float $bytes Nombre d'octets
     * @param int $precision Précision décimale
     * @return string Taille formatée
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
     * Vérifie si une variable est définie et non vide
     *
     * @param string $key Clé de la variable
     * @return bool True si définie et non vide
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== '';
    }
}
