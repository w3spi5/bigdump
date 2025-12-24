<?php

declare(strict_types=1);

namespace BigDump\Services;

/**
 * Analyzes SQL dump files to determine optimal import strategy.
 *
 * Samples the file content to detect:
 * - File size category (tiny/small/medium/large/massive)
 * - Average bytes per line
 * - Bulk INSERT patterns (multi-value INSERTs)
 * - Estimated total lines
 */
class FileAnalysisService
{
    /**
     * File size categories with RAM usage targets.
     * Larger files warrant more aggressive RAM utilization.
     */
    private const FILE_CATEGORIES = [
        'tiny'    => ['max_bytes' => 10 * 1024 * 1024,          'ram_pct' => 0.15, 'label' => 'Tiny (<10MB)'],
        'small'   => ['max_bytes' => 50 * 1024 * 1024,          'ram_pct' => 0.20, 'label' => 'Small (<50MB)'],
        'medium'  => ['max_bytes' => 500 * 1024 * 1024,         'ram_pct' => 0.40, 'label' => 'Medium (<500MB)'],
        'large'   => ['max_bytes' => 2 * 1024 * 1024 * 1024,    'ram_pct' => 0.60, 'label' => 'Large (<2GB)'],
        'massive' => ['max_bytes' => PHP_INT_MAX,                'ram_pct' => 0.75, 'label' => 'Massive (2GB+)'],
    ];

    private int $sampleSizeBytes;

    public function __construct(int $sampleSizeBytes = 1048576) // 1MB default
    {
        $this->sampleSizeBytes = $sampleSizeBytes;
    }

    /**
     * Analyze a file and return characteristics for batch sizing.
     *
     * @param string $filepath Full path to the file
     * @param bool $isGzip Whether this is a gzip file
     * @return FileAnalysisResult Analysis results
     */
    public function analyze(string $filepath, bool $isGzip = false): FileAnalysisResult
    {
        $fileSize = $isGzip ? 0 : (int) @filesize($filepath);
        $category = $this->calculateCategory($fileSize, $isGzip);
        $categoryInfo = self::FILE_CATEGORIES[$category];

        $sample = $this->readSample($filepath, $isGzip);
        $sampleLines = substr_count($sample, "\n");
        $sampleBytes = strlen($sample);

        // Calculate average bytes per line (default 200 if no lines found)
        $avgBytesPerLine = $sampleLines > 0 ? $sampleBytes / $sampleLines : 200.0;

        // Estimate total lines based on file size and average bytes per line
        $estimatedLines = $fileSize > 0 ? (int) ceil($fileSize / $avgBytesPerLine) : null;

        // Detect bulk INSERT patterns
        $isBulkInsert = $this->detectBulkInserts($sample);

        return new FileAnalysisResult(
            fileSize: $fileSize,
            category: $category,
            categoryLabel: $categoryInfo['label'],
            estimatedLines: $estimatedLines,
            avgBytesPerLine: $avgBytesPerLine,
            isBulkInsert: $isBulkInsert,
            targetRamUsage: $categoryInfo['ram_pct'],
            isGzip: $isGzip,
            isEstimate: $isGzip || $fileSize === 0
        );
    }

    /**
     * Read a sample from the file for analysis.
     */
    private function readSample(string $filepath, bool $isGzip): string
    {
        if ($isGzip && function_exists('gzopen')) {
            $handle = @gzopen($filepath, 'rb');
            if ($handle === false) {
                return '';
            }
            $sample = @gzread($handle, $this->sampleSizeBytes);
            gzclose($handle);
            return $sample ?: '';
        }

        $sample = @file_get_contents($filepath, false, null, 0, $this->sampleSizeBytes);
        return $sample ?: '';
    }

    /**
     * Detect if the file contains bulk INSERT statements (multi-value INSERTs).
     * These are much faster to process and allow larger batch sizes.
     */
    private function detectBulkInserts(string $sample): bool
    {
        // Look for INSERT INTO ... VALUES pattern with multiple value sets
        // Pattern: INSERT INTO table VALUES (...), (...) OR INSERT INTO table (cols) VALUES (...), (...)
        return (bool) preg_match('/INSERT\s+INTO\s+\S+\s+(?:\([^)]+\)\s+)?VALUES\s*\([^)]+\)\s*,/i', $sample);
    }

    /**
     * Determine file size category.
     */
    private function calculateCategory(int $fileSize, bool $isGzip): string
    {
        // Gzip files: default to 'medium' (conservative)
        if ($isGzip || $fileSize === 0) {
            return 'medium';
        }

        foreach (self::FILE_CATEGORIES as $category => $info) {
            if ($fileSize < $info['max_bytes']) {
                return $category;
            }
        }

        return 'massive';
    }

    /**
     * Get category info (for UI display).
     */
    public function getCategoryInfo(string $category): array
    {
        return self::FILE_CATEGORIES[$category] ?? self::FILE_CATEGORIES['medium'];
    }

    /**
     * Get all category definitions.
     */
    public static function getCategories(): array
    {
        return self::FILE_CATEGORIES;
    }

    /**
     * Check if SQL file contains CREATE TABLE statement for a specific table.
     *
     * Scans the beginning of the file (up to 10MB) for CREATE TABLE statements.
     * Supports both `tablename` and tablename formats.
     *
     * @param string $filepath Full path to SQL file
     * @param string $tableName Table name to search for (without backticks)
     * @return bool True if CREATE TABLE for the table is found
     */
    public function hasCreateTableFor(string $filepath, string $tableName): bool
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return false;
        }

        // Determine if file is gzipped
        $isGzip = str_ends_with(strtolower($filepath), '.gz');

        // Read up to 10MB for analysis
        $maxBytes = 10 * 1024 * 1024;

        try {
            // Open file (handle both regular and gzip files)
            if ($isGzip && function_exists('gzopen')) {
                $handle = @gzopen($filepath, 'rb');
                if ($handle === false) {
                    return false;
                }
                $content = @gzread($handle, $maxBytes);
                gzclose($handle);
            } else {
                $content = @file_get_contents($filepath, false, null, 0, $maxBytes);
            }

            if ($content === false || $content === '') {
                return false;
            }

            // Escape special regex characters in table name
            $escapedTableName = preg_quote($tableName, '/');

            // Pattern: CREATE TABLE (IF NOT EXISTS)? [`]?{tableName}[`]?
            // Supports:
            // - CREATE TABLE tablename
            // - CREATE TABLE `tablename`
            // - CREATE TABLE IF NOT EXISTS tablename
            // - CREATE TABLE IF NOT EXISTS `tablename`
            $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . $escapedTableName . '`?\s*\(/i';

            return (bool) preg_match($pattern, $content);
        } catch (\Throwable $e) {
            // Handle errors gracefully - return false on any exception
            return false;
        }
    }
}

/**
 * Immutable result object from file analysis.
 */
class FileAnalysisResult
{
    public function __construct(
        public readonly int $fileSize,
        public readonly string $category,
        public readonly string $categoryLabel,
        public readonly ?int $estimatedLines,
        public readonly float $avgBytesPerLine,
        public readonly bool $isBulkInsert,
        public readonly float $targetRamUsage,
        public readonly bool $isGzip,
        public readonly bool $isEstimate
    ) {}

    /**
     * Convert to array for session storage.
     */
    public function toArray(): array
    {
        return [
            'file_size' => $this->fileSize,
            'category' => $this->category,
            'category_label' => $this->categoryLabel,
            'estimated_lines' => $this->estimatedLines,
            'avg_bytes_per_line' => $this->avgBytesPerLine,
            'is_bulk_insert' => $this->isBulkInsert,
            'target_ram_usage' => $this->targetRamUsage,
            'is_gzip' => $this->isGzip,
            'is_estimate' => $this->isEstimate,
        ];
    }

    /**
     * Reconstruct from session array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fileSize: $data['file_size'] ?? 0,
            category: $data['category'] ?? 'medium',
            categoryLabel: $data['category_label'] ?? 'Medium (<500MB)',
            estimatedLines: $data['estimated_lines'] ?? null,
            avgBytesPerLine: (float) ($data['avg_bytes_per_line'] ?? 200.0),
            isBulkInsert: $data['is_bulk_insert'] ?? false,
            targetRamUsage: (float) ($data['target_ram_usage'] ?? 0.40),
            isGzip: $data['is_gzip'] ?? false,
            isEstimate: $data['is_estimate'] ?? true
        );
    }

    /**
     * Get formatted file size for display.
     */
    public function getFileSizeFormatted(): string
    {
        if ($this->fileSize === 0) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->fileSize;
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get target RAM usage as percentage string.
     */
    public function getTargetRamUsageFormatted(): string
    {
        return (int) ($this->targetRamUsage * 100) . '%';
    }
}
