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
     * Get AutoTuner metrics for UI display.
     *
     * @param int $currentLines Current line count for speed calculation
     * @return array AutoTuner metrics array
     */
    public function getAutoTunerMetrics(int $currentLines = 0): array
    {
        return $this->autoTuner->getMetrics($currentLines);
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
        $this->linesPerSession = $config->get('linespersession', 3000);

        // Initialize AutoTuner
        $this->autoTuner = new AutoTunerService($config);
        if ($this->autoTuner->isEnabled()) {
            $this->linesPerSession = $this->autoTuner->calculateOptimalBatchSize();
        }

        // Initialize INSERT batcher
        $insertBatchSize = (int) $config->get('insert_batch_size', 1000);
        $this->insertBatcher = new InsertBatcherService($insertBatchSize);
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

            // Check memory pressure for next session
            $pressure = $this->autoTuner->checkMemoryPressure();
            if ($pressure['adjustment']) {
                $this->linesPerSession = $this->autoTuner->getCurrentBatchSize();
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
            // Try to execute the final query
            $this->executeQuery($session, $pendingQuery);
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
}
