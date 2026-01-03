<?php

declare(strict_types=1);

namespace BigDump\Core;

use Phar;

/**
 * PharContext - PHAR detection and path resolution utilities
 *
 * Provides static methods to detect if running inside a PHAR archive
 * and resolve paths correctly for both PHAR and filesystem contexts.
 *
 * @package BigDump\Core
 * @author  w3spi5
 */
class PharContext
{
    /**
     * Cached result of PHAR detection
     * @var bool|null
     */
    private static ?bool $isPhar = null;

    /**
     * Cached PHAR running path
     * @var string|null
     */
    private static ?string $pharPath = null;

    /**
     * Checks if currently running inside a PHAR archive
     *
     * @return bool True if running inside PHAR
     */
    public static function isPhar(): bool
    {
        if (self::$isPhar === null) {
            self::$isPhar = Phar::running(false) !== '';
        }

        return self::$isPhar;
    }

    /**
     * Gets the root path for internal PHAR files
     *
     * When running from PHAR, returns the phar:// protocol path.
     * When running from filesystem, returns the project root.
     *
     * @return string Internal root path (phar:// path or filesystem path)
     */
    public static function getPharRoot(): string
    {
        if (self::isPhar()) {
            return Phar::running(true);
        }

        // Fallback: assume we're in src/Core, go up two levels
        return dirname(__DIR__, 2);
    }

    /**
     * Gets the external root path (filesystem path next to PHAR)
     *
     * When running from PHAR, returns the directory containing the PHAR file.
     * When running from filesystem, returns the project root.
     *
     * @return string External filesystem path
     */
    public static function getExternalRoot(): string
    {
        if (self::isPhar()) {
            if (self::$pharPath === null) {
                self::$pharPath = Phar::running(false);
            }
            return dirname(self::$pharPath);
        }

        // Fallback: assume we're in src/Core, go up two levels
        return dirname(__DIR__, 2);
    }

    /**
     * Gets the path to the external configuration file
     *
     * Config file is always external (next to PHAR) to allow user modification.
     *
     * @param string $filename Config filename (default: bigdump-config.php)
     * @return string Full path to config file
     */
    public static function getConfigPath(string $filename = 'bigdump-config.php'): string
    {
        return self::getExternalRoot() . '/' . $filename;
    }

    /**
     * Gets the uploads directory path
     *
     * Uploads are always external (next to PHAR) since PHAR is read-only.
     * Uses config value if provided, otherwise defaults to ./uploads/
     *
     * @param string|null $configuredPath Path from config, or null for default
     * @return string Full path to uploads directory
     */
    public static function getUploadsPath(?string $configuredPath = null): string
    {
        $externalRoot = self::getExternalRoot();

        if ($configuredPath !== null && $configuredPath !== '') {
            // If path is absolute, use as-is
            if (str_starts_with($configuredPath, '/') || preg_match('/^[A-Za-z]:/', $configuredPath)) {
                return rtrim($configuredPath, '/\\');
            }
            // Relative path: resolve from external root
            return $externalRoot . '/' . ltrim($configuredPath, '/\\');
        }

        // Default: uploads/ next to PHAR
        return $externalRoot . '/uploads';
    }

    /**
     * Gets the internal path for templates
     *
     * Templates are inside the PHAR archive.
     *
     * @return string Full path to templates directory
     */
    public static function getTemplatesPath(): string
    {
        return self::getPharRoot() . '/templates';
    }

    /**
     * Gets the internal path for source files
     *
     * Source files are inside the PHAR archive.
     *
     * @return string Full path to src directory
     */
    public static function getSrcPath(): string
    {
        return self::getPharRoot() . '/src';
    }

    /**
     * Gets the internal path for assets
     *
     * Assets are inside the PHAR archive (for inlining).
     *
     * @return string Full path to assets directory
     */
    public static function getAssetsPath(): string
    {
        return self::getPharRoot() . '/assets';
    }

    /**
     * Resolves a path relative to the PHAR internal root
     *
     * @param string $relativePath Path relative to PHAR root
     * @return string Full resolved path
     */
    public static function resolveInternalPath(string $relativePath): string
    {
        return self::getPharRoot() . '/' . ltrim($relativePath, '/\\');
    }

    /**
     * Resolves a path relative to the external root
     *
     * @param string $relativePath Path relative to external root
     * @return string Full resolved path
     */
    public static function resolveExternalPath(string $relativePath): string
    {
        return self::getExternalRoot() . '/' . ltrim($relativePath, '/\\');
    }

    /**
     * Resets cached values (for testing purposes)
     *
     * @return void
     */
    public static function resetCache(): void
    {
        self::$isPhar = null;
        self::$pharPath = null;
    }
}
