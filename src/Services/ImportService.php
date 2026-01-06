<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\Database;
use BigDump\Models\FileHandler;
use BigDump\Models\SqlParser;
use BigDump\Models\ImportSession;
use BigDump\Services\AutoTunerService;
use BigDump\Services\InsertBatcherService;
use BigDump\Services\FileAnalysisService;
use BigDump\Services\FileAnalysisResult;
use RuntimeException;

/**
 * ImportService Class - Main import service.
 *
 * This service orchestrates the entire import process:
 * - Opening and reading the dump file
 * - Parsing SQL queries
 * - Executing queries in the database
 * - Managing staggered import sessions
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class ImportService
{
    /**
     * Configuration.
     * @var Config
     */
    private Config $config;

    /**
     * Database handler.
     * @var Database
     */
    private Database $database;

    /**
     * File handler.
     * @var FileHandler
     */
    private FileHandler $fileHandler;

    /**
     * SQL parser.
     * @var SqlParser
     */
    private SqlParser $sqlParser;

    /**
     * Number of lines per session.
     * @var int
     */
    private int $linesPerSession;

    /**
     * Auto-tuning service.
     * @var AutoTunerService
     */
    private AutoTunerService $autoTuner;

    /**
     * INSERT batcher service.
     * @var InsertBatcherService
     */
    private InsertBatcherService $insertBatcher;

    /**
     * File analysis service.
     * @var FileAnalysisService
     */
    private FileAnalysisService $fileAnalyzer;

    /**
     * COMMIT frequency - how many batch flushes between COMMITs.
     * Conservative: 1 (every batch), Aggressive: 3 (every 3 batches)
     * @var int
     */
    private int $commitFrequency;

    /**
     * Counter for batches since last COMMIT.
     * @var int
     */
    private int $batchesSinceCommit = 0;

    /**
     * Whether auto-aggressive mode was activated for this import.
     * @var bool
     */
    private bool $autoAggressiveActivated = false;

    /**
     * Get AutoTuner metrics for UI display.
     *
     * @param int $currentLines Current line count for speed calculation
     * @return array AutoTuner metrics array
     */
    public function getAutoTunerMetrics(int $currentLines = 0): array
    {
        $metrics = $this->autoTuner->getMetrics($currentLines);
        $metrics['auto_aggressive_activated'] = $this->autoAggressiveActivated;
        return $metrics;
    }

    /**
     * Constructor.
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->database = new Database($config);
        $this->fileHandler = new FileHandler($config);
        $this->sqlParser = new SqlParser($config);
        $this->linesPerSession = $config->get('linespersession', 5000);

        // Initialize AutoTuner
        $this->autoTuner = new AutoTunerService($config);
        if ($this->autoTuner->isEnabled()) {
            $this->linesPerSession = $this->autoTuner->calculateOptimalBatchSize();
        }

        // Initialize INSERT batcher with profile-based configuration
        // Read both insert_batch_size and max_batch_bytes from config
        // These values are automatically set based on effective performance profile:
        // - Conservative: batch_size=2000, max_bytes=16MB
        // - Aggressive: batch_size=5000, max_bytes=32MB
        $insertBatchSize = (int) $config->get('insert_batch_size', 2000);
        $maxBatchBytes = (int) $config->get('max_batch_bytes', 16777216);
        $this->insertBatcher = new InsertBatcherService($insertBatchSize, $maxBatchBytes);

        // Initialize file analyzer
        $sampleSize = (int) $config->get('sample_size_bytes', 1048576);
        $this->fileAnalyzer = new FileAnalysisService($sampleSize);

        // Initialize COMMIT frequency from config
        // Conservative: 1 (every batch), Aggressive: 3 (every 3 batches)
        $this->commitFrequency = (int) $config->get('commit_frequency', 1);
    }

    /**
     * Check file size and automatically upgrade to aggressive profile if needed.
     *
     * This implements the auto-aggressive mode feature (v2.25+):
     * - Detects if file size exceeds auto_profile_threshold (default 100MB)
     * - Automatically upgrades to aggressive profile for faster imports
     * - Only activates if memory_limit allows aggressive mode
     *
     * @param string $filename Filename in uploads directory
     * @return bool True if auto-aggressive mode was activated
     */
    public function checkAutoAggressiveMode(string $filename): bool
    {
        // Get the full file path
        $filepath = $this->fileHandler->getFullPath($filename);

        if (!file_exists($filepath)) {
            return false;
        }

        // Get file size
        $fileSize = filesize($filepath);
        if ($fileSize === false) {
            return false;
        }

        // Get the auto-aggressive threshold (default 100MB)
        $threshold = (int) $this->config->get('auto_profile_threshold', 104857600);

        // Check if file exceeds threshold and current profile is conservative
        if ($fileSize > $threshold && $this->config->getEffectiveProfile() === 'conservative') {
            // Attempt to upgrade to aggressive profile
            $this->config->setTemporary('performance_profile', 'aggressive');

            // Check if upgrade was successful (memory requirements met)
            if ($this->config->getEffectiveProfile() === 'aggressive') {
                $this->autoAggressiveActivated = true;

                // Reinitialize components with new profile settings
                $this->reinitializeWithNewProfile();

                // Log the activation
                $fileSizeMB = round($fileSize / 1024 / 1024, 1);
                $thresholdMB = round($threshold / 1024 / 1024, 1);
                error_log(
                    "BigDump: Auto-aggressive mode activated for {$fileSizeMB}MB file " .
                    "(threshold: {$thresholdMB}MB)"
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Reinitialize components after profile change.
     *
     * Called when auto-aggressive mode is activated to update
     * INSERT batcher, COMMIT frequency, and other profile-dependent settings.
     *
     * @return void
     */
    private function reinitializeWithNewProfile(): void
    {
        // Update INSERT batcher with new profile settings
        $insertBatchSize = (int) $this->config->get('insert_batch_size', 2000);
        $maxBatchBytes = (int) $this->config->get('max_batch_bytes', 16777216);
        $this->insertBatcher = new InsertBatcherService($insertBatchSize, $maxBatchBytes);

        // Update COMMIT frequency
        $this->commitFrequency = (int) $this->config->get('commit_frequency', 1);

        // Update lines per session
        $this->linesPerSession = (int) $this->config->get('linespersession', 5000);

        // Recalculate optimal batch size with AutoTuner
        if ($this->autoTuner->isEnabled()) {
            $this->linesPerSession = $this->autoTuner->calculateOptimalBatchSize();
        }
    }

    /**
     * Analyze file and initialize file-aware auto-tuning.
     * Call this when starting a fresh import (offset = 0).
     *
     * @param string $filename Filename in uploads directory
     * @return FileAnalysisResult|null Analysis result or null if analysis disabled
     */
    public function analyzeFile(string $filename): ?FileAnalysisResult
    {
        // Check for auto-aggressive mode first
        $this->checkAutoAggressiveMode($filename);

        if (!$this->autoTuner->isEnabled() || !$this->autoTuner->isFileAwareTuningEnabled()) {
            return null;
        }

        $filepath = $this->fileHandler->getFullPath($filename);
        $extension = $this->fileHandler->getExtension($filename);
        $isGzip = ($extension === 'gz');

        $analysis = $this->fileAnalyzer->analyze($filepath, $isGzip);
        $this->autoTuner->setFileAnalysis($analysis);
        $this->linesPerSession = $this->autoTuner->calculateOptimalBatchSize();

        return $analysis;
    }

    /**
     * Restore file analysis from session data.
     * Call this when resuming an import.
     *
     * @param array $analysisData Stored analysis data from ImportSession
     */
    public function restoreFileAnalysis(array $analysisData): void
    {
        if (!empty($analysisData)) {
            $this->autoTuner->restoreFileAnalysis($analysisData);
            if ($this->autoTuner->isEnabled()) {
                $this->linesPerSession = $this->autoTuner->calculateOptimalBatchSize();
            }
        }
    }

    /**
     * Executes an import session.
     *
     * @param ImportSession $session Import session
     * @return ImportSession Updated session
     * @throws RuntimeException In case of critical error
     */
    public function executeSession(ImportSession $session): ImportSession
    {
        // Start AutoTuner timing
        $this->autoTuner->startTiming($session->getStartLine());

        try {
            // Connect to the database
            $this->database->connect();

            // Open the file
            $this->openFile($session);

            // Initialize the parser with the session delimiter
            $this->sqlParser->setDelimiter($session->getDelimiter());
            $this->sqlParser->reset();

            // Restore parser state from session
            // CRITICAL: Only restore pendingQuery if offset > 0 (continuation session)
            // If offset = 0, this is a fresh start - any existing pendingQuery is stale
            $pendingQuery = $session->getPendingQuery();
            if ($pendingQuery !== '' && $session->getCurrentOffset() > 0) {
                // Validate pendingQuery - must start with a SQL keyword to be valid
                // If it's just VALUES like "(123, 456)..." it's corrupted (lost INSERT prefix)
                $trimmed = ltrim($pendingQuery);
                $isValidSql = preg_match('/^(INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SET|LOCK|UNLOCK|START|COMMIT|ROLLBACK|REPLACE|TRUNCATE|USE|GRANT|REVOKE|SHOW|DESCRIBE|EXPLAIN|CALL|DELIMITER|\/\*|--)/i', $trimmed);

                if ($isValidSql) {
                    $this->sqlParser->setCurrentQuery($pendingQuery);
                    $this->sqlParser->setInString($session->getInString(), $session->getActiveQuote());
                } else {
                    // Corrupted pendingQuery (e.g., just VALUES without INSERT)
                    // Clear it and let the file continue from offset
                    error_log("BigDump: Discarding corrupted pendingQuery: " . substr($pendingQuery, 0, 100));
                    $session->setPendingQuery('');
                }
            }

            // Empty the CSV table if needed
            $this->emptyCsvTableIfNeeded($session);

            // Process lines
            $this->processLines($session);

            // Flush any remaining batched INSERTs before ending session
            $this->flushInsertBatcher($session);

            // COMMIT at end of each session (critical with autocommit=0)
            $this->database->query('COMMIT');

            // Update the final offset
            $session->setCurrentOffset($this->fileHandler->tell());

            // Update the delimiter if changed
            $session->setDelimiter($this->sqlParser->getDelimiter());

            // Save parser state for next session
            $currentQuery = $this->sqlParser->getCurrentQuery();
            $session->setPendingQuery($currentQuery);
            $session->setInString($this->sqlParser->isInString());
            $session->setActiveQuote($this->sqlParser->getActiveQuote());

            // Clean up pending query on completion
            if ($session->isFinished() || $session->hasError()) {
                $session->setPendingQuery('');
            }

            // AutoTuner metrics
            $metrics = $this->autoTuner->getMetrics($session->getCurrentLine());
            $session->setBatchSize($metrics['batch_size']);
            $session->setMemoryUsage($metrics['php_memory_usage']);
            $session->setMemoryPercentage($metrics['memory_percentage']);
            $session->setSpeedLps($metrics['speed_lps']);
            $session->setAutoTuneAdjustment($metrics['adjustment']);

            // Store file analysis data in session for persistence
            if ($this->autoTuner->getFileAnalysis() !== null) {
                $session->setFileAnalysisData($this->autoTuner->getFileAnalysis()->toArray());
            }

            // Dynamic batch adaptation (replaces simple checkMemoryPressure)
            // Uses speed stability + memory history for smarter decisions
            if ($this->autoTuner->isEnabled() && $metrics['speed_lps'] > 0) {
                $adaptation = $this->autoTuner->adaptBatchSize(
                    (float) $metrics['speed_lps'],
                    (int) $metrics['memory_percentage']
                );
                if ($adaptation['action'] !== 'stable') {
                    $this->linesPerSession = $this->autoTuner->getCurrentBatchSize();
                }
            }

        } catch (RuntimeException $e) {
            $session->setError($e->getMessage());
            $session->setPendingQuery('');
        } finally {
            $this->fileHandler->close();
            $this->database->close();
        }

        // NOTE: Session saving is now handled by the controller
        // Do NOT call toSession() here - it conflicts with SSE's direct file writing

        return $session;
    }

    /**
     * Opens the file and positions the cursor.
     *
     * @param ImportSession $session Import session
     * @return void
     * @throws RuntimeException If the file cannot be opened
     */
    private function openFile(ImportSession $session): void
    {
        $filename = $session->getFilename();

        // Check extension for CSV files
        $extension = $this->fileHandler->getExtension($filename);

        if ($extension === 'csv') {
            $csvTable = $this->config->get('csv_insert_table', '');

            if (empty($csvTable)) {
                throw new RuntimeException(
                    'CSV file detected but csv_insert_table is not configured. ' .
                    'Please set the destination table in config.'
                );
            }
        }

        // Open the file
        $this->fileHandler->open($filename);

        // Set the session properties
        $session->setFileSize($this->fileHandler->getFileSize());
        $session->setGzipMode($this->fileHandler->isGzipMode());

        // Position at the correct offset
        // Use currentOffset (updated after each batch) instead of startOffset (initial value)
        $offset = $session->getCurrentOffset();

        if ($offset > 0) {
            if (!$this->fileHandler->seek($offset)) {
                throw new RuntimeException("Cannot seek to offset {$offset}");
            }
        }
    }

    /**
     * Empties the CSV table if this is the first session and the option is enabled.
     *
     * @param ImportSession $session Import session
     * @return void
     * @throws RuntimeException If deletion fails
     */
    private function emptyCsvTableIfNeeded(ImportSession $session): void
    {
        // Only on the first session
        if ($session->getStartLine() !== 1) {
            return;
        }

        $csvTable = $this->config->get('csv_insert_table', '');
        $preempty = $this->config->get('csv_preempty_table', false);

        if (empty($csvTable) || !$preempty) {
            return;
        }

        $extension = $this->fileHandler->getExtension($session->getFilename());

        if ($extension !== 'csv') {
            return;
        }

        // Validate table name to prevent SQL injection
        // MySQL table names can contain letters, digits, underscores, and dollar signs
        $safeTable = preg_replace('/[^a-zA-Z0-9_$]/', '', $csvTable);
        if ($safeTable !== $csvTable || empty($safeTable)) {
            throw new RuntimeException("Invalid table name: {$csvTable}");
        }

        // Delete data from the table
        $query = "DELETE FROM `{$safeTable}`";

        if (!$this->database->query($query)) {
            throw new RuntimeException(
                "Failed to empty table '{$safeTable}': " . $this->database->getLastError()
            );
        }
    }

    /**
     * Processes lines from the file.
     *
     * @param ImportSession $session Import session
     * @return void
     * @throws RuntimeException In case of error
     */
    private function processLines(ImportSession $session): void
    {
        // Use currentLine (updated after each batch) not startLine (initial value)
        $currentLine = $session->getCurrentLine();
        $maxLine = $currentLine + $this->linesPerSession;
        $isCsv = $this->fileHandler->getExtension($session->getFilename()) === 'csv';
        $csvTable = $this->config->get('csv_insert_table', '');
        $isFirstLine = ($session->getCurrentOffset() === 0);

        while ($session->getCurrentLine() < $maxLine || $this->sqlParser->isInString()) {
            // Read a line
            $line = $this->fileHandler->readLine();

            if ($line === false) {
                // End of file
                $this->handleEndOfFile($session);
                break;
            }

            // Remove BOM on the first line
            if ($isFirstLine) {
                $line = $this->fileHandler->removeBom($line);
                $isFirstLine = false;
            }

            // Process the line
            if ($isCsv) {
                $this->processCsvLine($session, $line, $csvTable);
            } else {
                $this->processSqlLine($session, $line);
            }

            $session->incrementLine();
        }
    }

    /**
     * Processes an SQL line.
     *
     * @param ImportSession $session Import session
     * @param string $line Line to process
     * @return void
     * @throws RuntimeException In case of SQL error
     */
    private function processSqlLine(ImportSession $session, string $line): void
    {
        $result = $this->sqlParser->parseLine($line);

        // Check for parsing errors
        if ($result['error'] !== null) {
            throw new RuntimeException(
                "Line {$session->getCurrentLine()}: {$result['error']}"
            );
        }

        // Execute the query if complete
        if ($result['query'] !== null) {
            $this->executeQuery($session, $result['query']);
        }
    }

    /**
     * Processes a CSV line.
     *
     * @param ImportSession $session Import session
     * @param string $line CSV line
     * @param string $table Destination table
     * @return void
     * @throws RuntimeException In case of error
     */
    private function processCsvLine(ImportSession $session, string $line, string $table): void
    {
        // Ignore invalid lines
        if (!$this->sqlParser->isValidCsvLine($line)) {
            return;
        }

        // Convert to INSERT
        $query = $this->sqlParser->csvToInsert($line, $table);

        // Execute the query
        $this->executeQuery($session, $query);
    }

    /**
     * Executes an SQL query.
     *
     * @param ImportSession $session Import session
     * @param string $query SQL query
     * @return void
     * @throws RuntimeException In case of SQL error
     */
    private function executeQuery(ImportSession $session, string $query): void
    {
        if (empty(trim($query))) {
            return;
        }

        // Process through INSERT batcher
        $result = $this->insertBatcher->process($query);

        // Execute any queries returned by the batcher
        foreach ($result['queries'] as $batchedQuery) {
            $this->executeQueryDirect($session, $batchedQuery);
            $this->checkBatchCommit();
        }
    }

    /**
     * Check if we should COMMIT based on batch frequency.
     *
     * Commits every N batch flushes based on commit_frequency config:
     * - Conservative (1): COMMIT after every batch flush
     * - Aggressive (3): COMMIT after every 3 batch flushes
     *
     * This reduces COMMIT overhead in aggressive mode while maintaining
     * data durability with periodic commits.
     */
    private function checkBatchCommit(): void
    {
        $this->batchesSinceCommit++;

        if ($this->batchesSinceCommit >= $this->commitFrequency) {
            $this->database->query('COMMIT');
            $this->batchesSinceCommit = 0;
        }
    }

    /**
     * Executes a SQL query directly (bypasses batching).
     *
     * @param ImportSession $session Import session
     * @param string $query SQL query
     * @return void
     * @throws RuntimeException In case of SQL error
     */
    private function executeQueryDirect(ImportSession $session, string $query): void
    {
        if (empty(trim($query))) {
            return;
        }

        if (!$this->database->query($query)) {
            $error = $this->database->getLastError();
            $lineNum = $session->getCurrentLine();

            // Truncate the query for display
            $displayQuery = strlen($query) > 500
                ? substr($query, 0, 500) . '...'
                : $query;

            throw new RuntimeException(
                "SQL Error at line {$lineNum}:\n" .
                "Query: {$displayQuery}\n" .
                "MySQL Error: {$error}"
            );
        }

        $session->incrementQueries();
    }

    /**
     * Flushes any remaining batched INSERTs.
     *
     * @param ImportSession $session Import session
     * @return void
     */
    private function flushInsertBatcher(ImportSession $session): void
    {
        $result = $this->insertBatcher->flush();
        foreach ($result['queries'] as $batchedQuery) {
            $this->executeQueryDirect($session, $batchedQuery);
            $this->checkBatchCommit();
        }
    }

    /**
     * Handles the end of the file.
     *
     * @param ImportSession $session Import session
     * @return void
     * @throws RuntimeException If a query is incomplete
     */
    private function handleEndOfFile(ImportSession $session): void
    {
        // Check if there's a pending incomplete query
        $pendingQuery = $this->sqlParser->getPendingQuery();

        if ($pendingQuery !== null) {
            // Validate pendingQuery - must start with a SQL keyword to be valid
            $trimmed = ltrim($pendingQuery);
            $isValidSql = preg_match('/^(INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SET|LOCK|UNLOCK|START|COMMIT|ROLLBACK|REPLACE|TRUNCATE|USE|GRANT|REVOKE|SHOW|DESCRIBE|EXPLAIN|CALL|DELIMITER|\/\*|--)/i', $trimmed);

            if ($isValidSql) {
                // Try to execute the final query
                $this->executeQuery($session, $pendingQuery);
            } else {
                // Invalid/corrupted pending query - log and skip
                error_log("BigDump: Discarding invalid final query: " . substr($pendingQuery, 0, 200));
            }
        }

        // Flush any remaining batched INSERTs
        $this->flushInsertBatcher($session);

        // Execute post-queries to restore constraints
        $this->database->executePostQueries();

        // Check that we're not inside an unclosed string
        if ($this->sqlParser->isInString()) {
            throw new RuntimeException(
                "End of file reached with unclosed string. " .
                "The dump file may be corrupted or truncated."
            );
        }

        $session->setFinished();
    }

    /**
     * Retrieves the database handler.
     *
     * @return Database Database instance
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Retrieves the file handler.
     *
     * @return FileHandler FileHandler instance
     */
    public function getFileHandler(): FileHandler
    {
        return $this->fileHandler;
    }

    /**
     * Checks if the database is configured.
     *
     * @return bool True if configured
     */
    public function isDatabaseConfigured(): bool
    {
        return $this->config->isDatabaseConfigured();
    }

    /**
     * Tests the database connection.
     *
     * @return array{success: bool, message: string, charset: string} Test result
     */
    public function testConnection(): array
    {
        try {
            $this->database->connect();
            $charset = $this->database->getConnectionCharset();
            $this->database->close();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'charset' => $charset,
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'charset' => '',
            ];
        }
    }

    /**
     * Get INSERT batcher statistics.
     *
     * @return array INSERT batcher statistics
     */
    public function getInsertBatcherStatistics(): array
    {
        return $this->insertBatcher->getStatistics();
    }

    /**
     * Check if auto-aggressive mode was activated.
     *
     * @return bool True if auto-aggressive mode is active
     */
    public function isAutoAggressiveActivated(): bool
    {
        return $this->autoAggressiveActivated;
    }
}
