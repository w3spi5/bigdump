<?php

declare(strict_types=1);

namespace BigDump\Services;

use RuntimeException;

/**
 * CLI Optimizer Service - SQL Dump Optimization Orchestration
 *
 * Orchestrates the optimization process:
 * 1. Reads input file via CliFileReader
 * 2. Parses SQL via CliSqlParser
 * 3. Batches INSERTs via InsertBatcherService
 * 4. Writes optimized output incrementally
 *
 * Features:
 * - Profile-based batch sizing (conservative/aggressive)
 * - Progress reporting (time-based, every 2 seconds)
 * - Statistics tracking
 * - Error handling with cleanup
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class CliOptimizerService
{
    /**
     * Conservative profile defaults
     */
    public const PROFILE_CONSERVATIVE = [
        'batch_size' => 2000,
        'max_batch_bytes' => 16777216,  // 16MB
    ];

    /**
     * Aggressive profile defaults
     */
    public const PROFILE_AGGRESSIVE = [
        'batch_size' => 5000,
        'max_batch_bytes' => 33554432,  // 32MB
    ];

    /**
     * Progress update interval in seconds
     */
    private const PROGRESS_INTERVAL = 2;

    /**
     * Input file path
     */
    private string $inputPath;

    /**
     * Output file path
     */
    private string $outputPath;

    /**
     * Batch size
     */
    private int $batchSize;

    /**
     * Maximum batch bytes
     */
    private int $maxBatchBytes;

    /**
     * Force overwrite flag
     */
    private bool $force;

    /**
     * Profile name
     */
    private string $profile;

    /**
     * Output file handle
     * @var resource|null
     */
    private $outputHandle = null;

    /**
     * Processing statistics
     * @var array<string, int|float>
     */
    private array $statistics = [
        'lines_processed' => 0,
        'queries_written' => 0,
        'inserts_batched' => 0,
        'elapsed_time' => 0.0,
        'output_size' => 0,
    ];

    /**
     * Start time for elapsed tracking
     */
    private float $startTime;

    /**
     * Last progress update time
     */
    private float $lastProgressTime = 0;

    /**
     * Constructor
     *
     * @param string $inputPath Input file path
     * @param string $outputPath Output file path
     * @param array<string, mixed> $options Configuration options
     */
    public function __construct(string $inputPath, string $outputPath, array $options = [])
    {
        $this->inputPath = $inputPath;
        $this->outputPath = $outputPath;
        $this->batchSize = (int)($options['batchSize'] ?? self::PROFILE_CONSERVATIVE['batch_size']);
        $this->maxBatchBytes = (int)($options['maxBatchBytes'] ?? self::PROFILE_CONSERVATIVE['max_batch_bytes']);
        $this->force = (bool)($options['force'] ?? false);
        $this->profile = $options['profile'] ?? 'conservative';
    }

    /**
     * Runs the optimization process
     *
     * @return array{success: bool, statistics: array<string, int|float>, message: string}
     * @throws RuntimeException On fatal error
     */
    public function run(): array
    {
        $this->startTime = microtime(true);
        $this->lastProgressTime = $this->startTime;

        // Display header
        $this->displayHeader();

        // Validate output file
        if (file_exists($this->outputPath) && !$this->force) {
            throw new RuntimeException("Output file already exists. Use --force to overwrite.");
        }

        // Initialize components
        $reader = new CliFileReader($this->inputPath);
        $parser = new CliSqlParser();
        $batcher = new InsertBatcherService($this->batchSize, $this->maxBatchBytes);

        try {
            // Open input file
            $reader->open();

            // Open output file
            $this->outputHandle = @fopen($this->outputPath, 'wb');
            if ($this->outputHandle === false) {
                throw new RuntimeException("Cannot open output file: {$this->outputPath}");
            }

            fwrite(STDERR, "Processing...\n");

            // Process file line by line
            while (($line = $reader->readLine()) !== false) {
                $this->statistics['lines_processed']++;

                // Parse line for complete queries
                $parseResult = $parser->parseLine($line);

                if ($parseResult['error'] !== null) {
                    fwrite(STDERR, "Warning: {$parseResult['error']}\n");
                    continue;
                }

                if ($parseResult['query'] !== null) {
                    $this->processQuery($parseResult['query'], $batcher);
                }

                // Update progress (time-based)
                $this->updateProgress($reader);
            }

            // Handle pending query at EOF
            $pendingQuery = $parser->getPendingQuery();
            if ($pendingQuery !== null) {
                $this->processQuery($pendingQuery, $batcher);
            }

            // Flush remaining batched INSERTs
            $flushResult = $batcher->flush();
            foreach ($flushResult['queries'] as $query) {
                $this->writeQuery($query);
            }

            // Close files
            $reader->close();
            fclose($this->outputHandle);
            $this->outputHandle = null;

            // Finalize statistics
            $this->statistics['elapsed_time'] = microtime(true) - $this->startTime;
            $this->statistics['output_size'] = filesize($this->outputPath) ?: 0;

            // Get batcher statistics
            $batcherStats = $batcher->getStatistics();
            $this->statistics['inserts_batched'] = $batcherStats['batched_inserts'];
            $this->statistics['batched_queries_executed'] = $batcherStats['executed_queries'];
            $this->statistics['reduction_ratio'] = $batcherStats['reduction_ratio'];

            // Display summary
            $this->displaySummary();

            return [
                'success' => true,
                'statistics' => $this->statistics,
                'message' => 'Optimization completed successfully.',
            ];

        } catch (RuntimeException $e) {
            // Clean up on error
            $this->cleanup();
            throw $e;
        }
    }

    /**
     * Processes a single SQL query
     *
     * @param string $query SQL query
     * @param InsertBatcherService $batcher Batcher service
     */
    private function processQuery(string $query, InsertBatcherService $batcher): void
    {
        $result = $batcher->process($query);

        foreach ($result['queries'] as $outputQuery) {
            $this->writeQuery($outputQuery);
        }
    }

    /**
     * Writes a query to output file
     *
     * @param string $query Query to write
     */
    private function writeQuery(string $query): void
    {
        if ($this->outputHandle === null) {
            return;
        }

        $query = trim($query);
        if (empty($query)) {
            return;
        }

        // Ensure query ends with semicolon
        if (!str_ends_with($query, ';')) {
            $query .= ';';
        }

        fwrite($this->outputHandle, $query . "\n");
        $this->statistics['queries_written']++;
    }

    /**
     * Updates progress display (time-based)
     *
     * @param CliFileReader $reader File reader for progress calculation
     */
    private function updateProgress(CliFileReader $reader): void
    {
        $currentTime = microtime(true);

        if ($currentTime - $this->lastProgressTime < self::PROGRESS_INTERVAL) {
            return;
        }

        $this->lastProgressTime = $currentTime;
        $elapsed = $currentTime - $this->startTime;
        $elapsedFormatted = $this->formatTime($elapsed);

        $lines = $this->statistics['lines_processed'];
        $inserts = $this->statistics['inserts_batched'];

        // Calculate progress percentage
        $fileSize = $reader->getFileSize();
        $bytesRead = $reader->getBytesRead();

        if ($reader->isCompressed()) {
            // For compressed files, show bytes only
            fwrite(STDERR, sprintf(
                "\r[%s] Lines: %s | INSERTs batched: %s | Bytes read: %s",
                $elapsedFormatted,
                number_format($lines),
                number_format($inserts),
                $this->formatBytes($bytesRead)
            ));
        } else {
            // For uncompressed files, show percentage
            $progress = $fileSize > 0 ? round(($bytesRead / $fileSize) * 100) : 0;
            fwrite(STDERR, sprintf(
                "\r[%s] Lines: %s | INSERTs batched: %s | Progress: %d%%",
                $elapsedFormatted,
                number_format($lines),
                number_format($inserts),
                $progress
            ));
        }
    }

    /**
     * Displays header information
     */
    private function displayHeader(): void
    {
        $fileSize = filesize($this->inputPath) ?: 0;
        $basename = basename($this->inputPath);

        // Detect compression
        $compression = '';
        if (str_ends_with(strtolower($basename), '.gz')) {
            $compression = ' compressed';
        } elseif (str_ends_with(strtolower($basename), '.bz2')) {
            $compression = ' compressed';
        }

        fwrite(STDERR, "BigDump SQL Optimizer\n");
        fwrite(STDERR, "=====================\n");
        fwrite(STDERR, sprintf("Input:  %s (%s%s)\n", $basename, $this->formatBytes($fileSize), $compression));
        fwrite(STDERR, sprintf("Output: %s\n", basename($this->outputPath)));
        fwrite(STDERR, sprintf("Profile: %s (batch-size: %d)\n\n", $this->profile, $this->batchSize));
    }

    /**
     * Displays final summary
     */
    private function displaySummary(): void
    {
        fwrite(STDERR, "\n\n");
        fwrite(STDERR, "Complete!\n");
        fwrite(STDERR, "---------\n");
        fwrite(STDERR, sprintf("Input lines:     %s\n", number_format($this->statistics['lines_processed'])));
        fwrite(STDERR, sprintf("Output queries:  %s\n", number_format($this->statistics['queries_written'])));

        if ($this->statistics['inserts_batched'] > 0 && isset($this->statistics['batched_queries_executed'])) {
            $ratio = $this->statistics['reduction_ratio'] ?? 0;
            fwrite(STDERR, sprintf(
                "INSERTs batched: %s -> %s queries (%.1f:1 reduction)\n",
                number_format($this->statistics['inserts_batched']),
                number_format($this->statistics['batched_queries_executed']),
                $ratio
            ));
        }

        fwrite(STDERR, sprintf("Time elapsed:    %.1f seconds\n", $this->statistics['elapsed_time']));
        fwrite(STDERR, sprintf("Output size:     %s\n", $this->formatBytes($this->statistics['output_size'])));
    }

    /**
     * Formats time in MM:SS format
     *
     * @param float $seconds Seconds
     * @return string Formatted time
     */
    private function formatTime(float $seconds): string
    {
        $minutes = (int)floor($seconds / 60);
        $secs = (int)floor($seconds % 60);
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Formats bytes in human-readable format
     *
     * @param int|float $bytes Bytes
     * @return string Formatted size
     */
    private function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(0, $bytes);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 1) . ' ' . $units[$pow];
    }

    /**
     * Cleans up partial output on error
     */
    private function cleanup(): void
    {
        if ($this->outputHandle !== null) {
            @fclose($this->outputHandle);
            $this->outputHandle = null;
        }

        // Delete partial output file
        if (file_exists($this->outputPath)) {
            @unlink($this->outputPath);
            fwrite(STDERR, "Cleaned up partial output file.\n");
        }
    }

    /**
     * Gets processing statistics
     *
     * @return array<string, int|float>
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }
}
