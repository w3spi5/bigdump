<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\Database;
use BigDump\Models\FileHandler;
use BigDump\Models\SqlParser;
use BigDump\Models\ImportSession;
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
 * @author  Refactorisation MVC
 * @version 2.4
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
        // Generate unique session key based on filename
        $sessionKey = 'bigdump_pending_' . md5($session->getFilename());

        try {
            // Connect to the database
            $this->database->connect();

            // Open the file
            $this->openFile($session);

            // Initialize the parser with the session delimiter
            $this->sqlParser->setDelimiter($session->getDelimiter());
            $this->sqlParser->reset();

            // Restore parser state from PHP session (pendingQuery can be large)
            $pendingQuery = $_SESSION[$sessionKey] ?? '';
            if ($pendingQuery !== '') {
                $this->sqlParser->setCurrentQuery($pendingQuery);
                $this->sqlParser->setInString($session->getInString());
            }

            // Empty the CSV table if needed
            $this->emptyCsvTableIfNeeded($session);

            // Process lines
            $this->processLines($session);

            // Update the final offset
            $session->setCurrentOffset($this->fileHandler->tell());

            // Update the delimiter if changed
            $session->setDelimiter($this->sqlParser->getDelimiter());

            // Save parser state for next session
            $currentQuery = $this->sqlParser->getCurrentQuery();
            if ($currentQuery !== '') {
                $_SESSION[$sessionKey] = $currentQuery;
            } else {
                unset($_SESSION[$sessionKey]);
            }
            $session->setInString($this->sqlParser->isInString());

            // Clean up session on completion
            if ($session->isFinished() || $session->hasError()) {
                unset($_SESSION[$sessionKey]);
            }

        } catch (RuntimeException $e) {
            $session->setError($e->getMessage());
            unset($_SESSION[$sessionKey]);
        } finally {
            $this->fileHandler->close();
            $this->database->close();
        }

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
        $offset = $session->getStartOffset();

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
        $startLine = $session->getStartLine();
        $maxLine = $startLine + $this->linesPerSession;
        $isCsv = $this->fileHandler->getExtension($session->getFilename()) === 'csv';
        $csvTable = $this->config->get('csv_insert_table', '');
        $isFirstLine = ($session->getStartOffset() === 0);

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
