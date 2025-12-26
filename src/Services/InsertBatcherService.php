<?php

declare(strict_types=1);

namespace BigDump\Services;

/**
 * Groups consecutive simple INSERT statements into multi-value INSERTs
 *
 * Transforms:
 *   INSERT INTO t VALUES (1, 'a');
 *   INSERT INTO t VALUES (2, 'b');
 *   INSERT INTO t VALUES (3, 'c');
 *
 * Into:
 *   INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, 'c');
 *
 * This provides x10-50 speed improvement for dumps with simple INSERTs
 *
 * Features (v2.19+):
 * - Configurable batch size via constructor
 * - Configurable max_batch_bytes (16MB conservative, 32MB aggressive)
 * - INSERT IGNORE statement batching support
 * - Adaptive batch sizing based on average row size
 * - Extended batch efficiency metrics
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class InsertBatcherService
{
    /**
     * Maximum batch row count
     */
    private int $batchSize;

    /**
     * Whether batching is enabled
     */
    private bool $enabled;

    /**
     * Maximum batch size in bytes (configurable, default 16MB)
     */
    private int $maxBatchBytes;

    // Current batch state
    private ?string $currentTable = null;
    private ?string $currentPrefix = null;
    /** @var string[] */
    private array $currentValues = [];
    private int $currentBatchBytes = 0;

    // Statistics tracking
    private int $batchedCount = 0;
    private int $executedCount = 0;
    private int $totalBytesProcessed = 0;

    // Adaptive sizing: row size tracking
    private int $rowSizeSampleCount = 0;
    private int $rowSizeSampleTotal = 0;

    /**
     * Number of rows to sample for average row size calculation
     */
    private const ROW_SIZE_SAMPLE_LIMIT = 100;

    /**
     * Constructor.
     *
     * @param int $batchSize Maximum rows per batch (default 1000, recommend 2000 conservative / 5000 aggressive)
     * @param int $maxBatchBytes Maximum bytes per batch (default 16MB, max 32MB for aggressive mode)
     */
    public function __construct(int $batchSize = 1000, int $maxBatchBytes = 16777216)
    {
        $this->batchSize = $batchSize;
        $this->maxBatchBytes = $maxBatchBytes;
        $this->enabled = $batchSize > 0;
    }

    /**
     * Process a SQL query. Returns queries to execute (may be empty, one, or batched).
     *
     * @param string $query The SQL query to process
     * @return array{queries: string[], batched: bool} Queries to execute and whether batching occurred
     */
    public function process(string $query): array
    {
        if (!$this->enabled) {
            return ['queries' => [$query], 'batched' => false];
        }

        $trimmedQuery = trim($query);

        // Try to parse as simple INSERT (including INSERT IGNORE)
        $parsed = $this->parseSimpleInsert($trimmedQuery);

        if ($parsed === null) {
            // Not a simple INSERT - flush current batch and return this query
            $result = $this->flush();
            $result['queries'][] = $query;
            return $result;
        }

        // Track row size for adaptive sizing
        $this->trackRowSize(strlen($parsed['values']));

        // It's a simple INSERT - try to batch it
        return $this->addToBatch($parsed['table'], $parsed['prefix'], $parsed['values']);
    }

    /**
     * Flush any remaining batched INSERTs.
     * Must be called at end of session.
     *
     * @return array{queries: string[], batched: bool}
     */
    public function flush(): array
    {
        if (empty($this->currentValues)) {
            return ['queries' => [], 'batched' => false];
        }

        $query = $this->buildBatchedQuery();
        $this->resetBatch();

        return ['queries' => [$query], 'batched' => true];
    }

    /**
     * Parse a simple INSERT statement.
     *
     * OPTIMIZED: Uses string functions instead of complex regex.
     *
     * Matches: INSERT INTO table VALUES (...)
     * Matches: INSERT INTO table (cols) VALUES (...)
     * Matches: INSERT IGNORE INTO table VALUES (...)
     * Does NOT match: INSERT ... ON DUPLICATE KEY, INSERT ... SELECT, etc.
     *
     * @return array{table: string, prefix: string, values: string}|null
     */
    private function parseSimpleInsert(string $query): ?array
    {
        $upperQuery = strtoupper($query);

        // Quick rejection: must start with INSERT
        if (!str_starts_with($upperQuery, 'INSERT')) {
            return null;
        }

        // Quick rejection for complex INSERT types
        // Note: ON DUPLICATE KEY and SELECT are rejected, but IGNORE is now allowed
        if (
            str_contains($upperQuery, 'ON DUPLICATE') ||
            str_contains($upperQuery, ' SELECT ')
        ) {
            return null;
        }

        // Find VALUES position
        $valuesPos = strpos($upperQuery, 'VALUES');
        if ($valuesPos === false) {
            return null;
        }

        // Find the opening parenthesis after VALUES
        $openParenPos = strpos($query, '(', $valuesPos);
        if ($openParenPos === false) {
            return null;
        }

        // Extract prefix (everything before VALUES + "VALUES")
        $prefix = rtrim(substr($query, 0, $valuesPos)) . ' VALUES';

        // Extract table name from prefix
        $prefixUpper = strtoupper($prefix);

        // Handle INSERT IGNORE INTO pattern
        $intoPos = strpos($prefixUpper, 'INTO');
        if ($intoPos !== false) {
            $afterInto = ltrim(substr($prefix, $intoPos + 4));
        } else {
            // INSERT table (no INTO) - handle both INSERT and INSERT IGNORE
            $ignorePos = strpos($prefixUpper, 'IGNORE');
            if ($ignorePos !== false) {
                // "INSERT IGNORE table" pattern
                $afterInto = ltrim(substr($prefix, $ignorePos + 6));
            } else {
                // "INSERT table" pattern
                $afterInto = ltrim(substr($prefix, 6));
            }
        }

        // Table name is the first word (may include backticks)
        if (preg_match('/^(`?[\w]+`?(?:\.`?[\w]+`?)?)/', $afterInto, $tableMatch)) {
            $table = $tableMatch[1];
        } else {
            return null;
        }

        // Extract values (from opening paren to end, trimming semicolon)
        $values = rtrim(substr($query, $openParenPos), " \t\n\r;");

        // Validate that values starts with ( and ends with )
        if (!str_starts_with($values, '(') || !str_ends_with($values, ')')) {
            return null;
        }

        return [
            'table' => strtolower($table),
            'prefix' => $prefix,
            'values' => $values,
        ];
    }

    /**
     * Track row size for adaptive batch sizing.
     *
     * @param int $rowBytes Size of the row values in bytes
     */
    private function trackRowSize(int $rowBytes): void
    {
        if ($this->rowSizeSampleCount < self::ROW_SIZE_SAMPLE_LIMIT) {
            $this->rowSizeSampleTotal += $rowBytes;
            $this->rowSizeSampleCount++;
        }
    }

    /**
     * Calculate effective batch size based on average row size.
     *
     * Uses formula: effectiveBatchSize = min(maxBatchSize, maxBatchBytes / avgRowSize)
     * This prevents memory spikes when rows are larger than expected.
     *
     * @return int Effective batch size
     */
    private function getEffectiveBatchSize(): int
    {
        $avgRowSize = $this->getAverageRowSize();

        if ($avgRowSize <= 0) {
            return $this->batchSize;
        }

        // Calculate byte-limited batch size (account for ", " separator = 2 bytes)
        $byteBasedLimit = (int) floor($this->maxBatchBytes / ($avgRowSize + 2));

        // Return the minimum of configured batch size and byte-based limit
        return max(1, min($this->batchSize, $byteBasedLimit));
    }

    /**
     * Get average row size from samples.
     *
     * @return int Average row size in bytes, or 0 if no samples
     */
    private function getAverageRowSize(): int
    {
        if ($this->rowSizeSampleCount === 0) {
            return 0;
        }

        return (int) round($this->rowSizeSampleTotal / $this->rowSizeSampleCount);
    }

    /**
     * Add parsed INSERT to current batch.
     *
     * @return array{queries: string[], batched: bool}
     */
    private function addToBatch(string $table, string $prefix, string $values): array
    {
        // Check if we need to flush (different table/prefix, batch count full, or byte limit)
        $needsFlush = false;
        $valuesBytes = strlen($values);

        if ($this->currentTable !== null && $this->currentTable !== $table) {
            $needsFlush = true;
        }

        if ($this->currentPrefix !== null && $this->currentPrefix !== $prefix) {
            $needsFlush = true;
        }

        // Use effective batch size (considers adaptive sizing)
        $effectiveBatchSize = $this->getEffectiveBatchSize();
        if (count($this->currentValues) >= $effectiveBatchSize) {
            $needsFlush = true;
        }

        // Byte-based limit: flush if adding this value would exceed maxBatchBytes
        if ($this->currentBatchBytes + $valuesBytes + 2 > $this->maxBatchBytes) {
            $needsFlush = true;
        }

        $result = ['queries' => [], 'batched' => false];

        if ($needsFlush) {
            $result = $this->flush();
        }

        // Add to batch
        $this->currentTable = $table;
        $this->currentPrefix = $prefix;
        $this->currentValues[] = $values;
        $this->currentBatchBytes += $valuesBytes + 2; // +2 for ", "
        $this->batchedCount++;
        $this->totalBytesProcessed += $valuesBytes;

        return $result;
    }

    /**
     * Build the batched INSERT query.
     */
    private function buildBatchedQuery(): string
    {
        // Safety check: ensure prefix exists
        if ($this->currentPrefix === null || $this->currentPrefix === '') {
            // This should never happen, but if it does, return values as-is
            // to avoid executing invalid SQL
            throw new \RuntimeException(
                'InsertBatcher: Cannot build query without prefix. ' .
                'Values: ' . substr(implode(', ', $this->currentValues), 0, 200) . '...'
            );
        }

        $query = $this->currentPrefix . ' ' . implode(', ', $this->currentValues) . ';';
        $this->executedCount++;
        return $query;
    }

    /**
     * Reset batch state.
     */
    private function resetBatch(): void
    {
        $this->currentTable = null;
        $this->currentPrefix = null;
        $this->currentValues = [];
        $this->currentBatchBytes = 0;
    }

    /**
     * Get batching statistics with extended efficiency metrics.
     *
     * @return array{
     *     enabled: bool,
     *     batch_size: int,
     *     max_batch_bytes: int,
     *     batched_inserts: int,
     *     executed_queries: int,
     *     reduction_ratio: float,
     *     rows_batched: int,
     *     queries_executed: int,
     *     bytes_processed: int,
     *     avg_rows_per_batch: float,
     *     batch_efficiency: float,
     *     avg_row_size: int,
     *     effective_batch_size: int
     * }
     */
    public function getStatistics(): array
    {
        // Calculate reduction ratio
        $ratio = $this->executedCount > 0
            ? round($this->batchedCount / $this->executedCount, 1)
            : 0.0;

        // Calculate average rows per batch
        $avgRowsPerBatch = $this->executedCount > 0
            ? round($this->batchedCount / $this->executedCount, 1)
            : 0.0;

        // Calculate batch efficiency (0-1 scale)
        // Efficiency = 1 - (1 / reduction_ratio), capped at 0.99
        // A ratio of 1:1 (no batching) = 0% efficiency
        // A ratio of 10:1 = 90% efficiency
        $batchEfficiency = $this->batchedCount > 0 && $this->executedCount > 0
            ? min(0.99, 1 - ($this->executedCount / $this->batchedCount))
            : 0.0;

        return [
            // Original fields (backward compatible)
            'enabled' => $this->enabled,
            'batch_size' => $this->batchSize,
            'batched_inserts' => $this->batchedCount,
            'executed_queries' => $this->executedCount,
            'reduction_ratio' => $ratio,

            // Extended fields (v2.19+)
            'max_batch_bytes' => $this->maxBatchBytes,
            'rows_batched' => $this->batchedCount,
            'queries_executed' => $this->executedCount,
            'bytes_processed' => $this->totalBytesProcessed,
            'avg_rows_per_batch' => $avgRowsPerBatch,
            'batch_efficiency' => round($batchEfficiency, 3),
            'avg_row_size' => $this->getAverageRowSize(),
            'effective_batch_size' => $this->getEffectiveBatchSize(),
        ];
    }

    /**
     * Check if batching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get count of INSERTs currently in buffer.
     */
    public function getBufferCount(): int
    {
        return count($this->currentValues);
    }

    /**
     * Get the configured maximum batch bytes.
     *
     * @return int Maximum batch bytes
     */
    public function getMaxBatchBytes(): int
    {
        return $this->maxBatchBytes;
    }

    /**
     * Get the configured batch size.
     *
     * @return int Batch size
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }
}
