<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;

/**
 * AutoTunerService - Dynamic batch size optimization based on system resources
 *
 * Detects available RAM and PHP limits to calculate optimal linespersession.
 * Supports Windows (COM/WMI), Linux (/proc/meminfo), and fallback estimation.
 */
class AutoTunerService
{
    private Config $config;
    private bool $enabled;
    private int $minBatchSize;
    private int $maxBatchSize;
    private int $currentBatchSize;
    private ?int $forcedBatchSize = null;
    private ?array $systemResources = null;
    private ?string $lastAdjustment = null;
    private float $sessionStartTime = 0;
    private int $sessionStartLines = 0;

    /**
     * RAM profiles: threshold (bytes) => batch size
     */
    private const PROFILES = [
        536870912   => 5000,   // < 512 MB → 5,000
        1073741824  => 15000,  // < 1 GB   → 15,000
        2147483648  => 30000,  // < 2 GB   → 30,000
        4294967296  => 50000,  // < 4 GB   → 50,000
        PHP_INT_MAX => 80000,  // > 4 GB   → 80,000
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->enabled = (bool) $config->get('auto_tuning', true);
        $this->minBatchSize = (int) $config->get('min_batch_size', 3000);
        $this->maxBatchSize = (int) $config->get('max_batch_size', 100000);
        $this->currentBatchSize = (int) $config->get('linespersession', 3000);

        // Force batch size bypasses all auto-tuning calculations
        $forcedSize = $config->get('force_batch_size', 0);
        if ($forcedSize > 0) {
            $this->forcedBatchSize = (int) $forcedSize;
            $this->currentBatchSize = $this->forcedBatchSize;
        }
    }

    /**
     * Get all system resource information
     */
    public function getSystemResources(): array
    {
        if ($this->systemResources !== null) {
            return $this->systemResources;
        }

        $os = PHP_OS_FAMILY;
        $memory = null;

        // Try OS-specific methods first
        if ($os === 'Windows') {
            $memory = $this->getWindowsMemory();
        } elseif ($os === 'Linux') {
            $memory = $this->getLinuxMemory();
        }

        // Fallback if OS-specific failed
        if ($memory === null) {
            $memory = $this->getFallbackMemory();
        }

        $phpMemoryLimit = $this->parseMemoryLimit((string) ini_get('memory_limit'));
        $phpMemoryUsage = memory_get_usage(true);
        $phpPeakUsage = memory_get_peak_usage(true);

        $this->systemResources = [
            'os' => $os,
            'total_ram' => $memory['total'],
            'available_ram' => $memory['free'],
            'php_memory_limit' => $phpMemoryLimit,
            'php_memory_usage' => $phpMemoryUsage,
            'php_peak_usage' => $phpPeakUsage,
            'detection_method' => $memory['method'] ?? 'unknown',
        ];

        return $this->systemResources;
    }

    /**
     * Calculate optimal batch size based on system resources
     */
    public function calculateOptimalBatchSize(): int
    {
        // Force batch size bypasses all calculations
        if ($this->forcedBatchSize !== null) {
            return $this->forcedBatchSize;
        }

        if (!$this->enabled) {
            return $this->currentBatchSize;
        }

        $resources = $this->getSystemResources();

        // Constraint 1: Available system RAM
        $availableRam = $resources['available_ram'];

        // Constraint 2: PHP memory headroom
        $phpHeadroom = $resources['php_memory_limit'] - $resources['php_memory_usage'];

        // Take minimum of both constraints
        $effectiveLimit = min($availableRam, $phpHeadroom);

        // Apply 70% safety margin
        $safeBuffer = (int) ($effectiveLimit * 0.7);

        // Estimate ~500 bytes per SQL line (conservative average)
        $bytesPerLine = 500;
        $calculatedLines = (int) floor($safeBuffer / $bytesPerLine);

        // Lookup profile based on available RAM
        $profileBatch = $this->getProfileBatchSize($availableRam);

        // Take minimum of calculated and profile
        $optimal = min($calculatedLines, $profileBatch);

        // Clamp between min/max
        $this->currentBatchSize = max(
            $this->minBatchSize,
            min($optimal, $this->maxBatchSize)
        );

        return $this->currentBatchSize;
    }

    /**
     * Check memory pressure and suggest adjustments
     */
    public function checkMemoryPressure(): array
    {
        $usage = memory_get_usage(true);
        $limit = $this->parseMemoryLimit((string) ini_get('memory_limit'));
        $ratio = $limit > 0 ? $usage / $limit : 0;

        $adjustment = null;
        $newBatchSize = $this->currentBatchSize;

        if ($ratio > 0.8) {
            // High pressure: reduce batch size by 20%
            $newBatchSize = (int) ($this->currentBatchSize * 0.8);
            $newBatchSize = max($this->minBatchSize, $newBatchSize);
            $adjustment = [
                'action' => 'decrease',
                'factor' => 0.8,
                'reason' => 'memory_high',
                'old_batch' => $this->currentBatchSize,
                'new_batch' => $newBatchSize,
            ];
            $this->lastAdjustment = sprintf(
                'Batch reduced: %s → %s (Memory: %d%%)',
                number_format($this->currentBatchSize),
                number_format($newBatchSize),
                (int) ($ratio * 100)
            );
            $this->currentBatchSize = $newBatchSize;
        } elseif ($ratio < 0.5 && $this->currentBatchSize < $this->maxBatchSize) {
            // Low pressure: increase batch size by 20%
            $newBatchSize = (int) ($this->currentBatchSize * 1.2);
            $newBatchSize = min($this->maxBatchSize, $newBatchSize);
            $adjustment = [
                'action' => 'increase',
                'factor' => 1.2,
                'reason' => 'memory_low',
                'old_batch' => $this->currentBatchSize,
                'new_batch' => $newBatchSize,
            ];
            $this->lastAdjustment = sprintf(
                'Batch increased: %s → %s (Memory: %d%%)',
                number_format($this->currentBatchSize),
                number_format($newBatchSize),
                (int) ($ratio * 100)
            );
            $this->currentBatchSize = $newBatchSize;
        }

        return [
            'usage' => $usage,
            'limit' => $limit,
            'ratio' => $ratio,
            'percentage' => (int) ($ratio * 100),
            'adjustment' => $adjustment,
        ];
    }

    /**
     * Start timing for speed calculation
     */
    public function startTiming(int $startLines = 0): void
    {
        $this->sessionStartTime = microtime(true);
        $this->sessionStartLines = $startLines;
    }

    /**
     * Calculate lines per second
     */
    public function calculateSpeed(int $currentLines): float
    {
        if ($this->sessionStartTime === 0.0) {
            return 0.0;
        }

        $elapsed = microtime(true) - $this->sessionStartTime;
        if ($elapsed <= 0) {
            return 0.0;
        }

        $linesProcessed = $currentLines - $this->sessionStartLines;
        return $linesProcessed / $elapsed;
    }

    /**
     * Get metrics for UI display
     */
    public function getMetrics(int $currentLines = 0): array
    {
        $resources = $this->getSystemResources();
        $pressure = $this->checkMemoryPressure();

        return [
            'enabled' => $this->enabled,
            'os' => $resources['os'],
            'detection_method' => $resources['detection_method'],
            'total_ram' => $resources['total_ram'],
            'available_ram' => $resources['available_ram'],
            'total_ram_formatted' => $this->formatBytes($resources['total_ram']),
            'available_ram_formatted' => $this->formatBytes($resources['available_ram']),
            'php_memory_limit' => $resources['php_memory_limit'],
            'php_memory_limit_formatted' => $this->formatBytes($resources['php_memory_limit']),
            'php_memory_usage' => $resources['php_memory_usage'],
            'php_memory_usage_formatted' => $this->formatBytes($resources['php_memory_usage']),
            'memory_percentage' => $pressure['percentage'],
            'batch_size' => $this->currentBatchSize,
            'batch_size_formatted' => number_format($this->currentBatchSize),
            'min_batch_size' => $this->minBatchSize,
            'max_batch_size' => $this->maxBatchSize,
            'speed_lps' => $this->calculateSpeed($currentLines),
            'speed_formatted' => number_format($this->calculateSpeed($currentLines), 0) . ' l/s',
            'adjustment' => $this->lastAdjustment,
        ];
    }

    /**
     * Get current batch size
     */
    public function getCurrentBatchSize(): int
    {
        return $this->currentBatchSize;
    }

    /**
     * Set batch size (for loading from session)
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->currentBatchSize = max(
            $this->minBatchSize,
            min($batchSize, $this->maxBatchSize)
        );
    }

    /**
     * Check if auto-tuning is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get last adjustment message
     */
    public function getLastAdjustment(): ?string
    {
        return $this->lastAdjustment;
    }

    /**
     * Clear cached resources (for re-detection between sessions)
     */
    public function clearCache(): void
    {
        $this->systemResources = null;
    }

    // ========================================================================
    // PRIVATE METHODS - Memory Detection
    // ========================================================================

    /**
     * Get memory info on Windows using COM/WMI
     *
     * Note: Requires com_dotnet extension enabled in php.ini:
     *   extension=com_dotnet
     *
     * Without COM, falls back to generous estimation for local dev environments.
     */
    private function getWindowsMemory(): ?array
    {
        if (!class_exists('COM')) {
            // COM not available - return generous estimate for Windows dev environments
            // Most dev machines have 8-16GB RAM, assume 8GB with 50% available
            return [
                'total' => 8 * 1024 * 1024 * 1024,  // 8 GB
                'free' => 4 * 1024 * 1024 * 1024,   // 4 GB available
                'method' => 'windows_estimate',
            ];
        }

        try {
            $wmi = new \COM('WinMgmts://');
            $os = $wmi->ExecQuery('SELECT TotalVisibleMemorySize, FreePhysicalMemory FROM Win32_OperatingSystem');

            foreach ($os as $item) {
                return [
                    'total' => (int) $item->TotalVisibleMemorySize * 1024,
                    'free' => (int) $item->FreePhysicalMemory * 1024,
                    'method' => 'windows_com',
                ];
            }
        } catch (\Throwable $e) {
            // COM failed, return estimate
            return [
                'total' => 8 * 1024 * 1024 * 1024,
                'free' => 4 * 1024 * 1024 * 1024,
                'method' => 'windows_estimate',
            ];
        }

        return null;
    }

    /**
     * Get memory info on Linux by reading /proc/meminfo (no shell execution)
     */
    private function getLinuxMemory(): ?array
    {
        $meminfo = '/proc/meminfo';

        if (!is_readable($meminfo)) {
            return null;
        }

        $content = @file_get_contents($meminfo);
        if ($content === false) {
            return null;
        }

        $total = null;
        $available = null;

        if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $content, $matches)) {
            $total = (int) $matches[1] * 1024;
        }

        if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $content, $matches)) {
            $available = (int) $matches[1] * 1024;
        }

        if ($total !== null && $available !== null) {
            return [
                'total' => $total,
                'free' => $available,
                'method' => 'linux_procmeminfo',
            ];
        }

        return null;
    }

    /**
     * Fallback memory estimation based on PHP memory_limit
     */
    private function getFallbackMemory(): array
    {
        $limit = $this->parseMemoryLimit((string) ini_get('memory_limit'));

        // Conservative estimation: assume system has 4x PHP limit
        return [
            'total' => $limit * 4,
            'free' => $limit * 2,
            'method' => 'php_fallback',
        ];
    }

    /**
     * Parse PHP memory_limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            // Unlimited: assume 2GB
            return 2147483648;
        }

        $limit = trim($limit);
        $last = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Get batch size from profile based on available RAM
     */
    private function getProfileBatchSize(int $availableRam): int
    {
        foreach (self::PROFILES as $threshold => $batchSize) {
            if ($availableRam < $threshold) {
                return $batchSize;
            }
        }

        return self::PROFILES[PHP_INT_MAX];
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }
}
