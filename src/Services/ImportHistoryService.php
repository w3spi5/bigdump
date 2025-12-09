<?php

declare(strict_types=1);

namespace BigDump\Services;

/**
 * Import History Service
 *
 * Manages import history logs stored in a JSON file.
 *
 * @package BigDump\Services
 */
class ImportHistoryService
{
    /**
     * Path to history file.
     */
    private string $historyFile;

    /**
     * Maximum number of entries to keep.
     */
    private int $maxEntries;

    /**
     * Constructor.
     *
     * @param string $uploadDir Upload directory path
     * @param int $maxEntries Maximum entries to keep (default 50)
     */
    public function __construct(string $uploadDir, int $maxEntries = 50)
    {
        $this->historyFile = rtrim($uploadDir, '/') . '/.import_history.json';
        $this->maxEntries = $maxEntries;
    }

    /**
     * Add a new import entry to history.
     *
     * @param string $filename Imported filename
     * @param int $queriesExecuted Number of queries executed
     * @param int $linesProcessed Number of lines processed
     * @param int $bytesProcessed Number of bytes processed
     * @param bool $success Whether import was successful
     * @param string|null $error Error message if failed
     * @param float $duration Duration in seconds
     * @return void
     */
    public function addEntry(
        string $filename,
        int $queriesExecuted,
        int $linesProcessed,
        int $bytesProcessed,
        bool $success,
        ?string $error = null,
        float $duration = 0.0
    ): void {
        $history = $this->loadHistory();

        $entry = [
            'id' => uniqid('imp_'),
            'filename' => $filename,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'queries_executed' => $queriesExecuted,
            'lines_processed' => $linesProcessed,
            'bytes_processed' => $bytesProcessed,
            'size_formatted' => $this->formatBytes($bytesProcessed),
            'success' => $success,
            'error' => $error,
            'duration' => round($duration, 2),
            'duration_formatted' => $this->formatDuration($duration),
        ];

        // Add to beginning (most recent first)
        array_unshift($history, $entry);

        // Trim to max entries
        if (count($history) > $this->maxEntries) {
            $history = array_slice($history, 0, $this->maxEntries);
        }

        $this->saveHistory($history);
    }

    /**
     * Get all history entries.
     *
     * @param int|null $limit Limit number of entries (null = all)
     * @return array History entries
     */
    public function getHistory(?int $limit = null): array
    {
        $history = $this->loadHistory();

        if ($limit !== null && $limit > 0) {
            return array_slice($history, 0, $limit);
        }

        return $history;
    }

    /**
     * Get statistics summary.
     *
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        $history = $this->loadHistory();

        if (empty($history)) {
            return [
                'total_imports' => 0,
                'successful_imports' => 0,
                'failed_imports' => 0,
                'total_queries' => 0,
                'total_bytes' => 0,
                'total_bytes_formatted' => '0 B',
                'avg_duration' => 0,
                'last_import' => null,
            ];
        }

        $successful = array_filter($history, fn($e) => $e['success']);
        $failed = array_filter($history, fn($e) => !$e['success']);
        $totalQueries = array_sum(array_column($history, 'queries_executed'));
        $totalBytes = array_sum(array_column($history, 'bytes_processed'));
        $avgDuration = count($history) > 0
            ? array_sum(array_column($history, 'duration')) / count($history)
            : 0;

        return [
            'total_imports' => count($history),
            'successful_imports' => count($successful),
            'failed_imports' => count($failed),
            'total_queries' => $totalQueries,
            'total_bytes' => $totalBytes,
            'total_bytes_formatted' => $this->formatBytes($totalBytes),
            'avg_duration' => round($avgDuration, 2),
            'last_import' => $history[0] ?? null,
        ];
    }

    /**
     * Clear all history.
     *
     * @return void
     */
    public function clearHistory(): void
    {
        $this->saveHistory([]);
    }

    /**
     * Delete a specific entry by ID.
     *
     * @param string $id Entry ID
     * @return bool True if deleted
     */
    public function deleteEntry(string $id): bool
    {
        $history = $this->loadHistory();
        $initialCount = count($history);

        $history = array_filter($history, fn($e) => $e['id'] !== $id);
        $history = array_values($history); // Re-index

        if (count($history) < $initialCount) {
            $this->saveHistory($history);
            return true;
        }

        return false;
    }

    /**
     * Load history from file.
     *
     * @return array History entries
     */
    private function loadHistory(): array
    {
        if (!file_exists($this->historyFile)) {
            return [];
        }

        $content = file_get_contents($this->historyFile);
        if ($content === false || empty($content)) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save history to file.
     *
     * @param array $history History entries
     * @return void
     */
    private function saveHistory(array $history): void
    {
        $dir = dirname($this->historyFile);
        if (!is_dir($dir)) {
            return; // Can't create in non-existent directory
        }

        file_put_contents(
            $this->historyFile,
            json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /**
     * Format bytes to human readable.
     *
     * @param int $bytes Bytes
     * @return string Formatted string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Format duration to human readable.
     *
     * @param float $seconds Seconds
     * @return string Formatted string
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%dh %dm', $hours, $mins);
    }
}
