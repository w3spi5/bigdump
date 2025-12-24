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
 * @package BigDump\Services
 * @author  w3spi5
 */
class InsertBatcherService
{
    private int $batchSize;
    private bool $enabled;

    // Maximum batch size in bytes (16MB - safe for MySQL max_allowed_packet)
    private int $maxBatchBytes = 16777216;

    // Current batch state
    private ?string $currentTable = null;
    private ?string $currentPrefix = null;
    private array $currentValues = [];
    private int $batchedCount = 0;
    private int $executedCount = 0;
    private int $currentBatchBytes = 0;

    public function __construct(int $batchSize = 1000)
    {
        $this->batchSize = $batchSize;
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

        // Try to parse as simple INSERT
        $parsed = $this->parseSimpleInsert($trimmedQuery);

        if ($parsed === null) {
            // Not a simple INSERT - flush current batch and return this query
            $result = $this->flush();
            $result['queries'][] = $query;
            return $result;
        }

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
        if (
            str_contains($upperQuery, 'ON DUPLICATE') ||
            str_contains($upperQuery, 'IGNORE') ||
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
        $intoPos = strpos($prefixUpper, 'INTO');
        if ($intoPos !== false) {
            $afterInto = ltrim(substr($prefix, $intoPos + 4));
        } else {
            // INSERT table (no INTO)
            $afterInto = ltrim(substr($prefix, 6));
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

        if (count($this->currentValues) >= $this->batchSize) {
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
     * Get batching statistics.
     *
     * @return array{enabled: bool, batch_size: int, batched_inserts: int, executed_queries: int, reduction_ratio: float}
     */
    public function getStatistics(): array
    {
        $ratio = $this->executedCount > 0
            ? round($this->batchedCount / $this->executedCount, 1)
            : 0;

        return [
            'enabled' => $this->enabled,
            'batch_size' => $this->batchSize,
            'batched_inserts' => $this->batchedCount,
            'executed_queries' => $this->executedCount,
            'reduction_ratio' => $ratio,
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
}
