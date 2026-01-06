<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\FileHandler;
use BigDump\Services\FileAnalysisResult;

/**
 * AutoTunerService - Dynamic batch size optimization based on system resources
 *
 * Detects available RAM and PHP limits to calculate optimal linespersession.
 * Supports Windows (COM/WMI), Linux (/proc/meminfo), and fallback estimation.
 *
 * Features (v2.25+):
 * - Compression-aware batch sizing: adjusts batch sizes based on compression type
 * - Plain SQL gets 1.5x multiplier (less memory overhead)
 * - GZIP uses 1.0x baseline
 * - BZ2 uses 0.7x multiplier (higher decompression memory overhead)
 *
 * @package BigDump\Services
 * @author  w3spi5
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

    // File-aware tuning properties
    private ?FileAnalysisResult $fileAnalysis = null;
    private bool $fileAwareTuningEnabled = true;
    private array $speedHistory = [];
    private array $memoryHistory = [];

    // Performance profile properties (Task Group 4)
    private string $effectiveProfile = 'conservative';
    private bool $profileDowngraded = false;
    private float $profileMultiplier = 1.0;
    private float $safetyMargin = 0.8;

    // Memory caching properties (reduces redundant memory_get_usage calls)
    private ?int $cachedMemoryUsage = null;
    private float $memoryCacheTime = 0.0;
    private int $memoryCacheCallCount = 0;

    // Compression-aware tuning (v2.25+)
    private string $currentCompressionType = FileHandler::COMPRESSION_NONE;

    // Cache TTL constants
    private const MEMORY_CACHE_TTL = 1.0;          // 1 second TTL for memory cache
    private const SYSTEM_RESOURCES_TTL = 60.0;    // 60 seconds TTL for system resources
    private const AGGRESSIVE_MIN_RAM = 268435456; // 256MB minimum for aggressive mode

    /**
     * Compression-aware batch size multipliers (v2.25+)
     *
     * Different compression types have different memory characteristics:
     * - Plain SQL: Can use larger batches (1.5x) - data is what you see
     * - GZIP: Baseline (1.0x) - good balance of decompression overhead
     * - BZ2: Smaller batches (0.7x) - higher decompression memory overhead
     */
    private const COMPRESSION_MULTIPLIERS = [
        FileHandler::COMPRESSION_NONE => 1.5,  // Plain SQL: x1.5 (less memory overhead)
        FileHandler::COMPRESSION_GZIP => 1.0,  // GZIP: x1.0 (baseline)
        FileHandler::COMPRESSION_BZ2  => 0.7,  // BZ2: x0.7 (higher memory for decompression)
    ];

    /**
     * RAM x File Size batch reference table.
     * Values represent initial batch sizes for each RAM/FileCategory combination.
     * Array keys are RAM in GB, values are [tiny, small, medium, large, massive].
     */
    private const BATCH_REFERENCE = [
        1  => [10000,   30000,   50000,   80000,  100000],
        2  => [20000,   50000,   80000,  150000,  250000],
        3  => [25000,   70000,  120000,  200000,  350000],
        4  => [30000,   80000,  150000,  250000,  400000],
        5  => [40000,  100000,  200000,  350000,  500000],
        6  => [45000,  120000,  250000,  400000,  600000],
        8  => [50000,  150000,  300000,  500000,  750000],
        12 => [50000,  175000,  350000,  575000,  875000],
        16 => [50000,  200000,  400000,  650000, 1000000],
    ];

    /**
     * Category index mapping for BATCH_REFERENCE lookup
     */
    private const CATEGORY_INDEX = [
        'tiny'    => 0,
        'small'   => 1,
        'medium'  => 2,
        'large'   => 3,
        'massive' => 4,
    ];

    /**
     * History size for speed/memory tracking (last N samples)
     */
    private const HISTORY_SIZE = 5;

    /**
     * Multiplier for bulk INSERT files (more efficient to process)
     */
    private const BULK_INSERT_MULTIPLIER = 1.3;

    /**
     * Minimum batch size during dynamic adaptation
     */
    private const MIN_DYNAMIC_BATCH = 50000;

    /**
     * RAM profiles: threshold (bytes) => batch size
     * Aggressive profiles optimized for NVMe SSD systems (fallback for non-file-aware mode)
     */
    private const PROFILES = [
        536870912    => 30000,    // < 512 MB →   30,000 lines
        1073741824   => 80000,    // < 1 GB   →   80,000 lines
        2147483648   => 150000,   // < 2 GB   →  150,000 lines
        3221225472   => 220000,   // < 3 GB   →  220,000 lines
        4294967296   => 300000,   // < 4 GB   →  300,000 lines
        5368709120   => 380000,   // < 5 GB   →  380,000 lines
        6442450944   => 460000,   // < 6 GB   →  460,000 lines
        7516192768   => 540000,   // < 7 GB   →  540,000 lines
        8589934592   => 620000,   // < 8 GB   →  620,000 lines
        9663676416   => 700000,   // < 9 GB   →  700,000 lines
        10737418240  => 780000,   // < 10 GB  →  780,000 lines
        11811160064  => 860000,   // < 11 GB  →  860,000 lines
        12884901888  => 940000,   // < 12 GB  →  940,000 lines
        13958643712  => 1020000,  // < 13 GB  → 1,020,000 lines
        15032385536  => 1100000,  // < 14 GB  → 1,100,000 lines
        16106127360  => 1180000,  // < 15 GB  → 1,180,000 lines
        17179869184  => 1260000,  // < 16 GB  → 1,260,000 lines
        PHP_INT_MAX  => 1500000,  // > 16 GB  → 1,500,000 lines
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->enabled = (bool) $config->get('auto_tuning', true);
        $this->minBatchSize = (int) $config->get('min_batch_size', 10000);
        $this->currentBatchSize = (int) $config->get('linespersession', 50000);
        $this->fileAwareTuningEnabled = (bool) $config->get('file_aware_tuning', true);

        // Initialize performance profile settings
        $this->initializeProfileSettings($config);

        // Force batch size bypasses all auto-tuning calculations
        $forcedSize = $config->get('force_batch_size', 0);
        if ($forcedSize > 0) {
            $this->forcedBatchSize = (int) $forcedSize;
            $this->currentBatchSize = $this->forcedBatchSize;
        }

        // Check aggressive mode viability and warn if RAM is low
        $this->checkAggressiveModeViability();
    }

    /**
     * Initialize profile-based settings.
     *
     * Applies performance profile adjustments:
     * - Conservative: 80% safety margin, 1.5M max batch lines, 1.0x multiplier
     * - Aggressive: 70% safety margin, 2M max batch lines, 1.3x multiplier
     *
     * Note: AutoTuner's max_batch_size refers to maximum LINES per session,
     * NOT the Config's max_batch_size (which is a user-configurable ceiling).
     * These profile-based maximums are internal AutoTuner limits.
     */
    private function initializeProfileSettings(Config $config): void
    {
        // Get effective profile from Config (handles validation and downgrade)
        $this->effectiveProfile = $config->getEffectiveProfile();
        $this->profileDowngraded = $config->wasProfileDowngraded();

        // Apply profile-based settings with internal maximums
        // These are AutoTuner's internal ceilings, not user-configurable
        if ($this->effectiveProfile === 'aggressive') {
            // Aggressive mode: higher limits and smaller safety margin
            // Max batch ceiling: 2M lines (vs 1.5M conservative)
            $this->maxBatchSize = 2000000;
            $this->profileMultiplier = 1.3;  // +30% batch reference multiplier
            $this->safetyMargin = 0.7;       // 70% safety margin (vs 80%)
        } else {
            // Conservative mode: standard limits
            // Max batch ceiling: 1.5M lines
            $this->maxBatchSize = 1500000;
            $this->profileMultiplier = 1.0;
            $this->safetyMargin = 0.8;       // 80% safety margin
        }
    }

    /**
     * Check if aggressive mode has sufficient memory headroom.
     *
     * Emits a warning if available RAM is below 256MB threshold.
     * Does not downgrade - just warns about potential issues.
     */
    private function checkAggressiveModeViability(): void
    {
        if ($this->effectiveProfile !== 'aggressive') {
            return;
        }

        // Get system resources (this will populate cache)
        $resources = $this->getSystemResources();
        $availableRam = $resources['available_ram'];

        if ($availableRam < self::AGGRESSIVE_MIN_RAM) {
            $formattedRam = $this->formatBytes($availableRam);
            $formattedMin = $this->formatBytes(self::AGGRESSIVE_MIN_RAM);
            error_log(
                "BigDump: Aggressive mode enabled with low available RAM ({$formattedRam}). " .
                "Recommended minimum: {$formattedMin}. Performance may be degraded."
            );
        }
    }

    /** @var float Timestamp when system resources were cached */
    private float $systemResourcesCacheTime = 0.0;

    /**
     * Get all system resource information with TTL-based caching.
     *
     * System resources (OS detection, RAM) are relatively static and expensive to detect.
     * This method caches results for 60 seconds to avoid redundant OS/RAM detection
     * within the same PHP request or across rapid consecutive requests.
     */
    public function getSystemResources(): array
    {
        $now = microtime(true);

        // Check if cached data is still valid (within TTL)
        if ($this->systemResources !== null) {
            $cacheAge = $now - $this->systemResourcesCacheTime;
            if ($cacheAge < self::SYSTEM_RESOURCES_TTL) {
                // Return cached data with cache metadata
                return array_merge($this->systemResources, [
                    'cache_time' => $this->systemResourcesCacheTime,
                    'cache_ttl' => self::SYSTEM_RESOURCES_TTL,
                    'cache_age' => $cacheAge,
                ]);
            }
            // Cache expired - will refresh below
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

        // Cache the results with timestamp
        $this->systemResourcesCacheTime = $now;
        $this->systemResources = [
            'os' => $os,
            'total_ram' => $memory['total'],
            'available_ram' => $memory['free'],
            'php_memory_limit' => $phpMemoryLimit,
            'php_memory_usage' => $phpMemoryUsage,
            'php_peak_usage' => $phpPeakUsage,
            'detection_method' => $memory['method'] ?? 'unknown',
        ];

        // Return with cache metadata
        return array_merge($this->systemResources, [
            'cache_time' => $this->systemResourcesCacheTime,
            'cache_ttl' => self::SYSTEM_RESOURCES_TTL,
            'cache_age' => 0.0,
        ]);
    }

    /**
     * Set compression type for compression-aware batch sizing (v2.25+)
     *
     * @param string $compressionType One of FileHandler::COMPRESSION_* constants
     * @return void
     */
    public function setCompressionType(string $compressionType): void
    {
        $this->currentCompressionType = $compressionType;
    }

    /**
     * Set compression type from a filename (v2.25+)
     *
     * Convenience method that extracts compression type from filename extension.
     *
     * @param FileHandler $fileHandler FileHandler instance for detection
     * @param string $filename Filename to analyze
     * @return void
     */
    public function setCompressionTypeFromFile(FileHandler $fileHandler, string $filename): void
    {
        $this->currentCompressionType = $fileHandler->getCompressionType($filename);
    }

    /**
     * Get the current compression type
     *
     * @return string Current compression type
     */
    public function getCompressionType(): string
    {
        return $this->currentCompressionType;
    }

    /**
     * Get the compression multiplier for current compression type
     *
     * @return float Compression multiplier (0.7 - 1.5)
     */
    public function getCompressionMultiplier(): float
    {
        return self::COMPRESSION_MULTIPLIERS[$this->currentCompressionType] ?? 1.0;
    }

    /**
     * Get all compression multipliers (v2.25+)
     *
     * @return array<string, float> Compression type to multiplier mapping
     */
    public static function getCompressionMultipliers(): array
    {
        return self::COMPRESSION_MULTIPLIERS;
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

        // Use file-aware calculation if analysis is available
        if ($this->fileAwareTuningEnabled && $this->fileAnalysis !== null) {
            return $this->calculateFileAwareBatchSize();
        }

        // Fall back to RAM-only calculation
        return $this->calculateRamOnlyBatchSize();
    }

    /**
     * File-aware batch calculation using RAM x FileSize matrix.
     *
     * v2.25+: Applies compression multiplier for compression-aware sizing.
     */
    private function calculateFileAwareBatchSize(): int
    {
        $resources = $this->getSystemResources();
        $availableRamBytes = $resources['available_ram'];
        $availableRamGb = (int) floor($availableRamBytes / (1024 * 1024 * 1024));
        $availableRamGb = max(1, $availableRamGb); // Minimum 1GB for lookup

        $category = $this->fileAnalysis->category;
        $categoryIndex = self::CATEGORY_INDEX[$category] ?? 2; // Default to 'medium'

        // Find closest RAM tier
        $ramTier = $this->findClosestRamTier($availableRamGb);

        // Lookup base batch size
        $baseBatch = self::BATCH_REFERENCE[$ramTier][$categoryIndex] ?? 50000;

        // Apply bulk INSERT multiplier if detected (more efficient to process)
        if ($this->fileAnalysis->isBulkInsert) {
            $baseBatch = (int) ($baseBatch * self::BULK_INSERT_MULTIPLIER);
        }

        // Apply target RAM usage factor (larger files get more aggressive RAM usage)
        $targetUsage = $this->fileAnalysis->targetRamUsage;
        $adjustedBatch = (int) ($baseBatch * ($targetUsage / 0.40)); // 0.40 is baseline

        // v2.25+: Apply compression multiplier
        $compressionMultiplier = $this->getCompressionMultiplier();
        $adjustedBatch = (int) ($adjustedBatch * $compressionMultiplier);

        // Clamp to configured min/max
        $this->currentBatchSize = max(
            $this->minBatchSize,
            min($adjustedBatch, $this->maxBatchSize)
        );

        return $this->currentBatchSize;
    }

    /**
     * Find closest RAM tier in reference table.
     */
    private function findClosestRamTier(int $ramGb): int
    {
        $tiers = array_keys(self::BATCH_REFERENCE);
        sort($tiers);

        foreach ($tiers as $tier) {
            if ($ramGb <= $tier) {
                return $tier;
            }
        }

        return end($tiers); // Return highest tier if RAM exceeds all
    }

    /**
     * Existing RAM-only calculation (fallback when file analysis unavailable).
     *
     * This method is profile-aware:
     * - Conservative: 80% safety margin, 1.0x multiplier
     * - Aggressive: 70% safety margin, 1.3x multiplier
     *
     * v2.25+: Also applies compression multiplier.
     */
    private function calculateRamOnlyBatchSize(): int
    {
        $resources = $this->getSystemResources();

        // Constraint 1: Available system RAM
        $availableRam = $resources['available_ram'];

        // Constraint 2: PHP memory headroom
        $phpHeadroom = $resources['php_memory_limit'] - $resources['php_memory_usage'];

        // Take minimum of both constraints
        $effectiveLimit = min($availableRam, $phpHeadroom);

        // Apply profile-based safety margin (80% conservative, 70% aggressive)
        $safeBuffer = (int) ($effectiveLimit * $this->safetyMargin);

        // Estimate ~150 bytes per SQL line (aggressive for INSERT dumps)
        $bytesPerLine = 150;
        $calculatedLines = (int) floor($safeBuffer / $bytesPerLine);

        // Apply profile multiplier (1.0x conservative, 1.3x aggressive)
        $calculatedLines = (int) ($calculatedLines * $this->profileMultiplier);

        // v2.25+: Apply compression multiplier
        $compressionMultiplier = $this->getCompressionMultiplier();
        $calculatedLines = (int) ($calculatedLines * $compressionMultiplier);

        // Lookup profile based on available RAM
        $profileBatch = $this->getProfileBatchSize($availableRam);

        // Apply multipliers to profile batch as well
        $profileBatch = (int) ($profileBatch * $this->profileMultiplier * $compressionMultiplier);

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
     * Check memory pressure and suggest adjustments.
     *
     * This method uses cached memory values to reduce redundant memory_get_usage() calls.
     * Memory is cached for 1 second (MEMORY_CACHE_TTL) to balance accuracy with efficiency.
     */
    public function checkMemoryPressure(): array
    {
        $now = microtime(true);
        $cached = false;

        // Check if cached memory value is still valid
        if ($this->cachedMemoryUsage !== null) {
            $cacheAge = $now - $this->memoryCacheTime;
            if ($cacheAge < self::MEMORY_CACHE_TTL) {
                // Use cached value
                $usage = $this->cachedMemoryUsage;
                $cached = true;
            } else {
                // Cache expired - refresh
                $usage = memory_get_usage(true);
                $this->cachedMemoryUsage = $usage;
                $this->memoryCacheTime = $now;
                $this->memoryCacheCallCount++;
            }
        } else {
            // First call - populate cache
            $usage = memory_get_usage(true);
            $this->cachedMemoryUsage = $usage;
            $this->memoryCacheTime = $now;
            $this->memoryCacheCallCount++;
        }

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
            'cached' => $cached,
            'memory_call_count' => $this->memoryCacheCallCount,
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
     * Get metrics for UI display.
     *
     * Includes performance profile information for debugging and display.
     * v2.25+: Includes compression type and multiplier.
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
            // File-aware tuning metrics
            'file_aware_enabled' => $this->fileAwareTuningEnabled,
            'file_category' => $this->fileAnalysis?->category,
            'file_category_label' => $this->fileAnalysis?->categoryLabel,
            'target_ram_usage' => $this->fileAnalysis?->targetRamUsage,
            'is_bulk_insert' => $this->fileAnalysis?->isBulkInsert ?? false,
            'speed_trend' => $this->getSpeedTrend(),
            // Performance profile metrics (Task Group 4)
            'effective_profile' => $this->effectiveProfile,
            'profile_downgraded' => $this->profileDowngraded,
            'profile_multiplier' => $this->profileMultiplier,
            'safety_margin' => $this->safetyMargin,
            // Compression-aware metrics (v2.25+)
            'compression_type' => $this->currentCompressionType,
            'compression_multiplier' => $this->getCompressionMultiplier(),
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
     * Clear cached resources (for re-detection between sessions).
     *
     * Clears both system resources cache and memory usage cache.
     */
    public function clearCache(): void
    {
        $this->systemResources = null;
        $this->systemResourcesCacheTime = 0.0;
        $this->cachedMemoryUsage = null;
        $this->memoryCacheTime = 0.0;
    }

    // ========================================================================
    // FILE-AWARE TUNING METHODS
    // ========================================================================

    /**
     * Set file analysis result for file-aware batch calculation.
     */
    public function setFileAnalysis(FileAnalysisResult $analysis): void
    {
        $this->fileAnalysis = $analysis;
    }

    /**
     * Get current file analysis.
     */
    public function getFileAnalysis(): ?FileAnalysisResult
    {
        return $this->fileAnalysis;
    }

    /**
     * Restore file analysis from session data.
     */
    public function restoreFileAnalysis(array $data): void
    {
        if (!empty($data)) {
            $this->fileAnalysis = FileAnalysisResult::fromArray($data);
        }
    }

    /**
     * Check if file-aware tuning is enabled.
     */
    public function isFileAwareTuningEnabled(): bool
    {
        return $this->fileAwareTuningEnabled;
    }

    /**
     * Dynamic batch adaptation based on speed and memory history.
     * Call this after each session to adjust batch size for next session.
     *
     * Rules:
     * - RAM <30% + stable speed → increase 1.5x (max +100k)
     * - RAM >70% → decrease 0.7x (min 50k)
     * - Speed drop >30% → decrease 0.8x (query complexity changed)
     *
     * v2.25+: Considers compression type when adjusting max batch.
     */
    public function adaptBatchSize(float $currentSpeed, int $memoryPct): array
    {
        // Update history
        $this->speedHistory[] = $currentSpeed;
        $this->memoryHistory[] = $memoryPct;

        // Keep only last N samples
        if (count($this->speedHistory) > self::HISTORY_SIZE) {
            $this->speedHistory = array_slice($this->speedHistory, -self::HISTORY_SIZE);
        }
        if (count($this->memoryHistory) > self::HISTORY_SIZE) {
            $this->memoryHistory = array_slice($this->memoryHistory, -self::HISTORY_SIZE);
        }

        // Need at least 3 samples for stable decisions
        if (count($this->speedHistory) < 3) {
            return [
                'action' => 'stable',
                'reason' => 'collecting_samples',
                'old_batch' => $this->currentBatchSize,
                'new_batch' => $this->currentBatchSize,
            ];
        }

        $avgSpeed = array_sum($this->speedHistory) / count($this->speedHistory);
        $speedVariance = $this->calculateVariance($this->speedHistory);
        $avgMemory = array_sum($this->memoryHistory) / count($this->memoryHistory);

        $oldBatch = $this->currentBatchSize;
        $newBatch = $oldBatch;
        $action = 'stable';
        $reason = 'optimal';

        // v2.25+: Apply compression multiplier to max batch size
        $compressionMultiplier = $this->getCompressionMultiplier();
        $adjustedMaxBatch = (int) ($this->maxBatchSize * $compressionMultiplier);

        // RULE 1: Low memory + stable speed → INCREASE aggressively
        // Speed variance check: variance < 10% of mean squared indicates stability
        if ($avgMemory < 30 && $speedVariance < 0.1 * $avgSpeed * $avgSpeed) {
            $factor = 1.5;
            $maxIncrease = 100000;
            $newBatch = (int) min($oldBatch * $factor, $oldBatch + $maxIncrease);
            $newBatch = min($newBatch, $adjustedMaxBatch);
            $action = 'increase';
            $reason = 'ram_underutilized';
        }
        // RULE 2: High memory → DECREASE to prevent OOM
        elseif ($avgMemory > 70) {
            $factor = 0.7;
            $newBatch = (int) max($oldBatch * $factor, self::MIN_DYNAMIC_BATCH);
            $action = 'decrease';
            $reason = 'memory_pressure';
        }
        // RULE 3: Speed drop >30% → DECREASE (query complexity changed)
        elseif (count($this->speedHistory) >= 4) {
            $historyCount = count($this->speedHistory);
            $recentAvg = ($this->speedHistory[$historyCount - 1] + $this->speedHistory[$historyCount - 2]) / 2;
            $earlierAvg = ($this->speedHistory[0] + $this->speedHistory[1]) / 2;
            if ($earlierAvg > 0 && $recentAvg < $earlierAvg * 0.7) {
                $newBatch = (int) max($oldBatch * 0.8, self::MIN_DYNAMIC_BATCH);
                $action = 'decrease';
                $reason = 'speed_degradation';
            }
        }

        if ($newBatch !== $oldBatch) {
            $this->currentBatchSize = $newBatch;
            $this->lastAdjustment = sprintf(
                'Batch %s: %s → %s (%s)',
                $action === 'increase' ? 'increased' : 'decreased',
                number_format($oldBatch),
                number_format($newBatch),
                str_replace('_', ' ', $reason)
            );
        }

        return [
            'action' => $action,
            'reason' => $reason,
            'old_batch' => $oldBatch,
            'new_batch' => $newBatch,
            'avg_speed' => $avgSpeed,
            'avg_memory' => $avgMemory,
            'compression_multiplier' => $compressionMultiplier,
        ];
    }

    /**
     * Calculate variance of an array of values.
     */
    private function calculateVariance(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiff = 0.0;

        foreach ($values as $value) {
            $sumSquaredDiff += ($value - $mean) ** 2;
        }

        return $sumSquaredDiff / ($count - 1);
    }

    /**
     * Get speed trend indicator for UI.
     */
    public function getSpeedTrend(): string
    {
        if (count($this->speedHistory) < 3) {
            return 'calculating';
        }

        $historyCount = count($this->speedHistory);
        $recent = array_slice($this->speedHistory, -2);
        $earlier = array_slice($this->speedHistory, 0, 2);

        $recentAvg = array_sum($recent) / count($recent);
        $earlierAvg = array_sum($earlier) / count($earlier);

        if ($earlierAvg === 0.0) {
            return 'stable';
        }

        $changeRatio = $recentAvg / $earlierAvg;

        if ($changeRatio > 1.1) {
            return 'increasing';
        } elseif ($changeRatio < 0.9) {
            return 'decreasing';
        }

        return 'stable';
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
