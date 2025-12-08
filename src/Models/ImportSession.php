<?php

declare(strict_types=1);

namespace BigDump\Models;

/**
 * ImportSession Class - Import session state management
 *
 * This class encapsulates all data from an import session,
 * including statistics and progression state
 *
 * @package BigDump\Models
 * @author  w3spi5
 */
class ImportSession
{
    /**
     * File being imported
     * @var string
     */
    private string $filename = '';

    /**
     * Starting line number
     * @var int
     */
    private int $startLine = 1;

    /**
     * Starting offset in file
     * @var int
     */
    private int $startOffset = 0;

    /**
     * Current line number
     * @var int
     */
    private int $currentLine = 1;

    /**
     * Current offset in file
     * @var int
     */
    private int $currentOffset = 0;

    /**
     * Number of queries executed in this session
     * @var int
     */
    private int $sessionQueries = 0;

    /**
     * Total number of executed queries
     * @var int
     */
    private int $totalQueries = 0;

    /**
     * Total file size
     * @var int
     */
    private int $fileSize = 0;

    /**
     * Current SQL delimiter
     * @var string
     */
    private string $delimiter = ';';

    /**
     * Import finished
     * @var bool
     */
    private bool $finished = false;

    /**
     * Error encountered
     * @var string|null
     */
    private ?string $error = null;

    /**
     * Gzip mode
     * @var bool
     */
    private bool $gzipMode = false;

    /**
     * Pending incomplete query from previous session
     */
    private string $pendingQuery = '';

    /**
     * Whether parser was inside a string at end of session
     */
    private bool $inString = false;

    /**
     * Active quote character when in string (' or ")
     * @var string
     */
    private string $activeQuote = '';

    /**
     * Current batch size (lines per session)
     * @var int
     */
    private int $batchSize = 3000;

    /**
     * Memory usage in bytes
     * @var int
     */
    private int $memoryUsage = 0;

    /**
     * Memory usage percentage
     * @var int
     */
    private int $memoryPercentage = 0;

    /**
     * Processing speed in lines per second
     * @var float
     */
    private float $speedLps = 0.0;

    /**
     * AutoTune adjustment message
     * @var string|null
     */
    private ?string $autoTuneAdjustment = null;

    /**
     * Frozen estimated total lines (calculated once at ~5% progress)
     * @var int|null
     */
    private ?int $frozenLinesTotal = null;

    /**
     * Frozen estimated total queries (calculated once at ~5% progress)
     * @var int|null
     */
    private ?int $frozenQueriesTotal = null;

    /**
     * Creates a new session from request parameters
     *
     * @param string $filename Filename
     * @param int $startLine Starting line
     * @param int $startOffset Starting offset
     * @param int $totalQueries Total of previous queries
     * @param string $delimiter SQL delimiter
     * @param string $pendingQuery Pending incomplete query from previous session
     * @param bool $inString Whether parser was inside a string
     * @param string $activeQuote Active quote character when in string
     * @return self
     */
    public static function fromRequest(
        string $filename,
        int $startLine = 1,
        int $startOffset = 0,
        int $totalQueries = 0,
        string $delimiter = ';',
        string $pendingQuery = '',
        bool $inString = false,
        string $activeQuote = ''
    ): self {
        $session = new self();
        $session->filename = $filename;
        $session->startLine = max(1, $startLine);
        $session->currentLine = $session->startLine;
        $session->startOffset = max(0, $startOffset);
        $session->currentOffset = $session->startOffset;
        $session->totalQueries = max(0, $totalQueries);
        $session->delimiter = $delimiter;
        $session->pendingQuery = $pendingQuery;
        $session->inString = $inString;
        $session->activeQuote = $activeQuote;

        return $session;
    }

    /**
     * Retrieves filename
     *
     * @return string Filename
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Sets filename
     *
     * @param string $filename Filename
     * @return self
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Retrieves starting line
     *
     * @return int Starting line
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Retrieves current line
     *
     * @return int Current line
     */
    public function getCurrentLine(): int
    {
        return $this->currentLine;
    }

    /**
     * Increments line number
     *
     * @return self
     */
    public function incrementLine(): self
    {
        $this->currentLine++;
        return $this;
    }

    /**
     * Retrieves starting offset
     *
     * @return int Starting offset
     */
    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    /**
     * Retrieves current offset
     *
     * @return int Current offset
     */
    public function getCurrentOffset(): int
    {
        return $this->currentOffset;
    }

    /**
     * Sets current offset
     *
     * @param int $offset Offset
     * @return self
     */
    public function setCurrentOffset(int $offset): self
    {
        $this->currentOffset = $offset;
        return $this;
    }

    /**
     * Retrieves session query count
     *
     * @return int Number of queries
     */
    public function getSessionQueries(): int
    {
        return $this->sessionQueries;
    }

    /**
     * Increments query counter
     *
     * @return self
     */
    public function incrementQueries(): self
    {
        $this->sessionQueries++;
        $this->totalQueries++;
        return $this;
    }

    /**
     * Retrieves total query count
     *
     * @return int Total queries
     */
    public function getTotalQueries(): int
    {
        return $this->totalQueries;
    }

    /**
     * Retrieves file size
     *
     * @return int Size in bytes
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Sets file size
     *
     * @param int $size Size in bytes
     * @return self
     */
    public function setFileSize(int $size): self
    {
        $this->fileSize = $size;
        return $this;
    }

    /**
     * Gets frozen lines total estimate
     *
     * @return int|null Frozen estimate or null
     */
    public function getFrozenLinesTotal(): ?int
    {
        return $this->frozenLinesTotal;
    }

    /**
     * Gets frozen queries total estimate
     *
     * @return int|null Frozen estimate or null
     */
    public function getFrozenQueriesTotal(): ?int
    {
        return $this->frozenQueriesTotal;
    }

    /**
     * Retrieves SQL delimiter
     *
     * @return string Delimiter
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Sets SQL delimiter
     *
     * @param string $delimiter Delimiter
     * @return self
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Checks if import is finished
     *
     * @return bool True if finished
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }

    /**
     * Marks import as finished
     *
     * @return self
     */
    public function setFinished(): self
    {
        $this->finished = true;
        return $this;
    }

    /**
     * Checks if there is an error
     *
     * @return bool True if error
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Retrieves error
     *
     * @return string|null Error message
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Sets error
     *
     * @param string $error Error message
     * @return self
     */
    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }

    /**
     * Checks if gzip mode is active
     *
     * @return bool True if gzip mode
     */
    public function isGzipMode(): bool
    {
        return $this->gzipMode;
    }

    /**
     * Sets gzip mode
     *
     * @param bool $gzipMode Gzip mode
     * @return self
     */
    public function setGzipMode(bool $gzipMode): self
    {
        $this->gzipMode = $gzipMode;
        return $this;
    }


    /**
     * Gets pending query from previous session
     *
     * @return string Pending query
     */
    public function getPendingQuery(): string
    {
        return $this->pendingQuery;
    }

    /**
     * Sets pending query for next session
     *
     * @param string $pendingQuery Pending query
     * @return self
     */
    public function setPendingQuery(string $pendingQuery): self
    {
        $this->pendingQuery = $pendingQuery;
        return $this;
    }

    /**
     * Gets in-string state from previous session
     *
     * @return bool In-string state
     */
    public function getInString(): bool
    {
        return $this->inString;
    }

    /**
     * Sets in-string state for next session
     *
     * @param bool $inString In-string state
     * @return self
     */
    public function setInString(bool $inString): self
    {
        $this->inString = $inString;
        return $this;
    }

    /**
     * Gets active quote character
     *
     * @return string Active quote character ('' or "")
     */
    public function getActiveQuote(): string
    {
        return $this->activeQuote;
    }

    /**
     * Sets active quote character
     *
     * @param string $activeQuote Quote character
     * @return self
     */
    public function setActiveQuote(string $activeQuote): self
    {
        $this->activeQuote = $activeQuote;
        return $this;
    }

    /**
     * Gets current batch size
     *
     * @return int Batch size
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Sets batch size
     *
     * @param int $batchSize Batch size
     * @return self
     */
    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;
        return $this;
    }

    /**
     * Gets memory usage
     *
     * @return int Memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    /**
     * Sets memory usage
     *
     * @param int $usage Memory usage in bytes
     * @return self
     */
    public function setMemoryUsage(int $usage): self
    {
        $this->memoryUsage = $usage;
        return $this;
    }

    /**
     * Gets memory usage percentage
     *
     * @return int Memory percentage
     */
    public function getMemoryPercentage(): int
    {
        return $this->memoryPercentage;
    }

    /**
     * Sets memory usage percentage
     *
     * @param int $pct Memory percentage
     * @return self
     */
    public function setMemoryPercentage(int $pct): self
    {
        $this->memoryPercentage = $pct;
        return $this;
    }

    /**
     * Gets processing speed
     *
     * @return float Speed in lines per second
     */
    public function getSpeedLps(): float
    {
        return $this->speedLps;
    }

    /**
     * Sets processing speed
     *
     * @param float $speed Speed in lines per second
     * @return self
     */
    public function setSpeedLps(float $speed): self
    {
        $this->speedLps = $speed;
        return $this;
    }

    /**
     * Gets AutoTune adjustment message
     *
     * @return string|null Adjustment message
     */
    public function getAutoTuneAdjustment(): ?string
    {
        return $this->autoTuneAdjustment;
    }

    /**
     * Sets AutoTune adjustment message
     *
     * @param string|null $adj Adjustment message
     * @return self
     */
    public function setAutoTuneAdjustment(?string $adj): self
    {
        $this->autoTuneAdjustment = $adj;
        return $this;
    }

    /**
     * Calculates session statistics
     *
     * @return array<string, mixed> Statistics
     */
    public function getStatistics(): array
    {
        $linesThis = $this->currentLine - $this->startLine;
        $linesDone = $this->currentLine - 1;

        $bytesThis = $this->currentOffset - $this->startOffset;
        $bytesDone = $this->currentOffset;

        // Calculations for non-gzip files only
        $bytesTogo = $this->gzipMode ? null : max(0, $this->fileSize - $this->currentOffset);
        $bytesTotal = $this->gzipMode ? null : $this->fileSize;

        // Estimate lines/queries total based on observed ratio (bytes per line/query)
        $linesTotal = null;
        $linesTogo = null;
        $queriesTotal = null;
        $queriesTogo = null;

        if ($this->finished) {
            // Exact values when finished
            $linesTotal = $linesDone;
            $linesTogo = 0;
            $queriesTotal = $this->totalQueries;
            $queriesTogo = 0;
        } elseif (!$this->gzipMode && $bytesDone > 0 && $this->fileSize > 0) {
            // Calculate current estimates
            $bytesPerLine = $bytesDone / max(1, $linesDone);
            $currentLinesEstimate = (int) ceil($this->fileSize / $bytesPerLine);

            $currentQueriesEstimate = null;
            if ($this->totalQueries > 0) {
                $bytesPerQuery = $bytesDone / $this->totalQueries;
                $currentQueriesEstimate = (int) ceil($this->fileSize / $bytesPerQuery);
            }

            // Freeze estimates once we have processed 5% of the file (enough data for stable estimate)
            $progressPct = ($bytesDone / $this->fileSize) * 100;
            if ($progressPct >= 5 && $this->frozenLinesTotal === null) {
                $this->frozenLinesTotal = $currentLinesEstimate;
                $this->frozenQueriesTotal = $currentQueriesEstimate;
            }

            // Use frozen values if available, otherwise use current estimates
            $linesTotal = $this->frozenLinesTotal ?? $currentLinesEstimate;
            $linesTogo = max(0, $linesTotal - $linesDone);

            if ($this->frozenQueriesTotal !== null) {
                $queriesTotal = $this->frozenQueriesTotal;
                $queriesTogo = max(0, $queriesTotal - $this->totalQueries);
            } elseif ($currentQueriesEstimate !== null) {
                $queriesTotal = $currentQueriesEstimate;
                $queriesTogo = max(0, $queriesTotal - $this->totalQueries);
            }
        }

        // Percentages
        $pctDone = null;
        $pctThis = null;
        $pctTogo = null;

        if (!$this->gzipMode && $this->fileSize > 0) {
            $pctDone = min(100.0, round($this->currentOffset / $this->fileSize * 100, 2));
            $pctThis = min(100.0, round($bytesThis / $this->fileSize * 100, 2));
            $pctTogo = max(0.0, round(100 - $pctDone, 2));
        }

        return [
            // Lines
            'lines_this' => $linesThis,
            'lines_done' => $linesDone,
            'lines_togo' => $linesTogo,
            'lines_total' => $linesTotal,

            // Queries
            'queries_this' => $this->sessionQueries,
            'queries_done' => $this->totalQueries,
            'queries_togo' => $queriesTogo,
            'queries_total' => $queriesTotal,

            // Bytes
            'bytes_this' => $bytesThis,
            'bytes_done' => $bytesDone,
            'bytes_togo' => $bytesTogo,
            'bytes_total' => $bytesTotal,

            // Kilobytes
            'kb_this' => round($bytesThis / 1024, 2),
            'kb_done' => round($bytesDone / 1024, 2),
            'kb_togo' => $bytesTogo !== null ? round($bytesTogo / 1024, 2) : null,
            'kb_total' => $bytesTotal !== null ? round($bytesTotal / 1024, 2) : null,

            // Megabytes
            'mb_this' => round($bytesThis / 1048576, 2),
            'mb_done' => round($bytesDone / 1048576, 2),
            'mb_togo' => $bytesTogo !== null ? round($bytesTogo / 1048576, 2) : null,
            'mb_total' => $bytesTotal !== null ? round($bytesTotal / 1048576, 2) : null,

            // Percentages
            'pct_this' => $pctThis,
            'pct_done' => $pctDone,
            'pct_togo' => $pctTogo,
            'pct_total' => 100,

            // Status
            'finished' => $this->finished,
            'gzip_mode' => $this->gzipMode,
            'estimates_frozen' => $this->frozenLinesTotal !== null,

            // AutoTuner metrics
            'batch_size' => $this->batchSize,
            'memory_usage' => $this->memoryUsage,
            'memory_percentage' => $this->memoryPercentage,
            'speed_lps' => $this->speedLps,
            'auto_tune_adjustment' => $this->autoTuneAdjustment,
        ];
    }

    /**
     * Generates parameters for next session (URL-safe, no large data)
     *
     * @return array<string, mixed> Parameters
     */
    public function getNextSessionParams(): array
    {
        return [
            'start' => $this->currentLine,
            'fn' => $this->filename,
            'foffset' => $this->currentOffset,
            'totalqueries' => $this->totalQueries,
            'delimiter' => $this->delimiter,
            'instring' => $this->inString ? '1' : '0',
            'activequote' => $this->activeQuote,
        ];
    }

    /**
     * Saves import state to PHP session.
     *
     * @return void
     */
    public function toSession(): void
    {
        $_SESSION['import'] = [
            'filename' => $this->filename,
            'start_line' => $this->currentLine,
            'offset' => $this->currentOffset,
            'total_queries' => $this->totalQueries,
            'delimiter' => $this->delimiter,
            'in_string' => $this->inString,
            'active_quote' => $this->activeQuote,
            'pending_query' => $this->pendingQuery,
            'file_size' => $this->fileSize,
            'frozen_lines_total' => $this->frozenLinesTotal,
            'frozen_queries_total' => $this->frozenQueriesTotal,
            'active' => true,
        ];
    }

    /**
     * Restores import state from PHP session.
     *
     * @return self|null ImportSession or null if no active session
     */
    public static function fromSession(): ?self
    {
        if (empty($_SESSION['import']['active']) || empty($_SESSION['import']['filename'])) {
            return null;
        }

        $data = $_SESSION['import'];
        $session = self::fromRequest(
            $data['filename'],
            $data['start_line'] ?? 1,
            $data['offset'] ?? 0,
            $data['total_queries'] ?? 0,
            $data['delimiter'] ?? ';',
            $data['pending_query'] ?? '',
            $data['in_string'] ?? false,
            $data['active_quote'] ?? ''
        );

        // Restore frozen estimates if available
        if (isset($data['frozen_lines_total'])) {
            $session->frozenLinesTotal = $data['frozen_lines_total'];
        }
        if (isset($data['frozen_queries_total'])) {
            $session->frozenQueriesTotal = $data['frozen_queries_total'];
        }

        return $session;
    }

    /**
     * Clears import session from PHP session.
     *
     * @return void
     */
    public static function clearSession(): void
    {
        unset($_SESSION['import']);
    }

    /**
     * Checks if an import session is active.
     *
     * @return bool True if active session exists
     */
    public static function hasActiveSession(): bool
    {
        return !empty($_SESSION['import']['active']) && !empty($_SESSION['import']['filename']);
    }

    /**
     * Converts session to array for XML/JSON
     *
     * @return array<string, mixed> Session data
     */
    public function toArray(): array
    {
        $stats = $this->getStatistics();

        return array_merge($stats, [
            'filename' => $this->filename,
            'current_line' => $this->currentLine,
            'current_offset' => $this->currentOffset,
            'delimiter' => $this->delimiter,
            'error' => $this->error,
        ]);
    }
}
