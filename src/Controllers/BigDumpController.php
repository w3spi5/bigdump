<?php

declare(strict_types=1);

namespace BigDump\Controllers;

use BigDump\Config\Config;
use BigDump\Core\Request;
use BigDump\Core\Response;
use BigDump\Core\View;
use BigDump\Models\ImportSession;
use BigDump\Services\ImportService;
use BigDump\Services\AjaxService;
use BigDump\Services\SseService;
use BigDump\Services\ImportHistoryService;

/**
 * BigDumpController Class - Main controller
 *
 * This controller handles all application actions:
 * - Displaying the home page with file list
 * - File upload
 * - File deletion
 * - Executing import sessions
 * - AJAX responses
 *
 * @package BigDump\Controllers
 * @author  w3spi5
 */
class BigDumpController
{
    /**
     * Configuration.
     * @var Config
     */
    protected Config $config;

    /**
     * Request.
     * @var Request
     */
    protected Request $request;

    /**
     * Response.
     * @var Response
     */
    protected Response $response;

    /**
     * View.
     * @var View
     */
    protected View $view;

    /**
     * Import service.
     * @var ImportService
     */
    protected ImportService $importService;

    /**
     * AJAX service.
     * @var AjaxService
     */
    protected AjaxService $ajaxService;

    /**
     * Import history service.
     * @var ImportHistoryService
     */
    protected ImportHistoryService $historyService;

    /**
     * Constructor.
     *
     * @param Config $config Configuration
     * @param Request $request Request
     * @param Response $response Response
     * @param View $view View
     */
    public function __construct(
        Config $config,
        Request $request,
        Response $response,
        View $view
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->response = $response;
        $this->view = $view;

        $this->importService = new ImportService($config);
        $this->ajaxService = new AjaxService($config);
        $this->historyService = new ImportHistoryService($config->get('upload_dir', 'uploads/'));
    }

    /**
     * Action: Home page.
     *
     * Displays the list of available files and the upload form.
     *
     * @return void
     */
    public function index(): void
    {
        $fileHandler = $this->importService->getFileHandler();

        // Check database configuration
        $dbConfigured = $this->importService->isDatabaseConfigured();
        $connectionInfo = null;

        if ($dbConfigured) {
            $connectionInfo = $this->importService->testConnection();
        }

        // Retrieve the file list
        $files = $fileHandler->listFiles();

        // Check if the directory is writable
        $uploadEnabled = $fileHandler->isUploadDirWritable();

        // Maximum upload size
        $uploadMaxSize = $this->config->getUploadMaxFilesize();

        // Predefined file name
        $predefinedFile = $this->config->get('filename', '');

        // Prepare data for the view
        $this->view->assign([
            'files' => $files,
            'dbConfigured' => $dbConfigured,
            'connectionInfo' => $connectionInfo,
            'uploadEnabled' => $uploadEnabled,
            'uploadMaxSize' => $uploadMaxSize,
            'uploadDir' => $fileHandler->getUploadDir(),
            'predefinedFile' => $predefinedFile,
            'dbName' => $this->config->get('db_name'),
            'dbServer' => $this->config->get('db_server'),
            'testMode' => $this->config->get('test_mode', false),
        ]);

        // Render the view
        $content = $this->view->render('home');
        $this->response->setContent($content);
    }

    /**
     * Action: File upload.
     *
     * @return void
     */
    public function upload(): void
    {
        $fileHandler = $this->importService->getFileHandler();
        $file = $this->request->file('dumpfile');

        $result = ['success' => false, 'message' => 'No file provided'];

        if ($file !== null) {
            $result = $fileHandler->upload($file);
        }

        // Prepare data for the view
        $this->view->assign([
            'uploadResult' => $result,
            'files' => $fileHandler->listFiles(),
            'dbConfigured' => $this->importService->isDatabaseConfigured(),
            'connectionInfo' => $this->importService->testConnection(),
            'uploadEnabled' => $fileHandler->isUploadDirWritable(),
            'uploadMaxSize' => $this->config->getUploadMaxFilesize(),
            'uploadDir' => $fileHandler->getUploadDir(),
            'predefinedFile' => $this->config->get('filename', ''),
            'dbName' => $this->config->get('db_name'),
            'dbServer' => $this->config->get('db_server'),
            'testMode' => $this->config->get('test_mode', false),
        ]);

        $content = $this->view->render('home');
        $this->response->setContent($content);
    }

    /**
     * Action: File deletion.
     *
     * @return void
     */
    public function delete(): void
    {
        $fileHandler = $this->importService->getFileHandler();
        $filename = $this->request->input('delete', '');

        $result = ['success' => false, 'message' => 'No file specified'];

        if (!empty($filename)) {
            // Check that it's not the script itself
            if (basename($filename) === $this->request->getScriptName()) {
                $result = ['success' => false, 'message' => 'Cannot delete the script itself'];
            } else {
                $result = $fileHandler->delete($filename);
            }
        }

        // Prepare data for the view
        $this->view->assign([
            'deleteResult' => $result,
            'files' => $fileHandler->listFiles(),
            'dbConfigured' => $this->importService->isDatabaseConfigured(),
            'connectionInfo' => $this->importService->testConnection(),
            'uploadEnabled' => $fileHandler->isUploadDirWritable(),
            'uploadMaxSize' => $this->config->getUploadMaxFilesize(),
            'uploadDir' => $fileHandler->getUploadDir(),
            'predefinedFile' => $this->config->get('filename', ''),
            'dbName' => $this->config->get('db_name'),
            'dbServer' => $this->config->get('db_server'),
            'testMode' => $this->config->get('test_mode', false),
        ]);

        $content = $this->view->render('home');
        $this->response->setContent($content);
    }

    /**
     * Action: Import start (first page).
     *
     * @return void
     */
    public function startImport(): void
    {
        $filename = $this->request->input('fn', '');

        if (empty($filename)) {
            $filename = $this->config->get('filename', '');
        }

        // Check that the file exists
        $fileHandler = $this->importService->getFileHandler();

        if (!$fileHandler->exists($filename)) {
            $this->view->assign([
                'error' => "File not found: {$filename}",
            ]);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Clear any previous session and create new one
        ImportSession::clearSession();
        $session = ImportSession::fromRequest(
            $filename,
            1,
            0,
            0,
            $this->config->get('delimiter', ';'),
            '',
            false,
            ''
        );
        $session->toSession();

        // Redirect to import page using full URL to avoid browser issues
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUri = $this->request->getScriptUri();
        $path = ($baseUri === '' || $baseUri === '/') ? '/import' : $baseUri . '/import';
        
        $this->response->redirect($protocol . '://' . $host . $path);
    }

    /**
     * Action: Import page display.
     *
     * For AJAX/SSE mode: Just display the UI, SSE handles the actual import.
     * For noscript mode: Execute one batch and redirect.
     *
     * @return void
     */
    public function import(): void
    {
        // Try session first, fallback to URL params (noscript mode)
        $session = ImportSession::fromSession();
        $isNoscriptMode = false;

        if (!$session) {
            // Fallback: read from URL params (noscript continuation)
            $session = ImportSession::fromRequest(
                $this->request->input('fn', ''),
                $this->request->getInt('start', 1),
                $this->request->getInt('foffset', 0),
                $this->request->getInt('totalqueries', 0),
                $this->request->input('delimiter', ';'),
                '',
                $this->request->getInt('instring', 0) === 1,
                $this->request->input('activequote', '')
            );
            $isNoscriptMode = true;
        }

        // Validate parameters
        if (empty($session->getFilename())) {
            $this->view->assign(['error' => 'No filename specified']);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Get file size for progress bar (needed even in SSE mode for initial display)
        $fileHandler = $this->importService->getFileHandler();
        $filename = $session->getFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isGzip = ($extension === 'gz');

        if (!$isGzip && $session->getFileSize() === 0) {
            // Set file size for non-gzip files so progress bar works
            $fullPath = $fileHandler->getUploadDir() . '/' . $filename;
            if (file_exists($fullPath)) {
                $session->setFileSize((int) filesize($fullPath));
                $session->setGzipMode(false);
            }
        } elseif ($isGzip) {
            $session->setGzipMode(true);
        }

        // For AJAX/SSE mode: DON'T execute here, just display the interface
        // SSE endpoint will do all the work
        // For noscript mode: Execute one batch
        if ($isNoscriptMode || !$this->ajaxService->isAjaxEnabled()) {
            $session = $this->importService->executeSession($session);
            $session->toSession(); // Save progress to session
        }

        // Prepare data for the view
        $this->view->assign([
            'session' => $session,
            'statistics' => $session->getStatistics(),
            'testMode' => $this->config->get('test_mode', false),
            'ajaxEnabled' => $this->ajaxService->isAjaxEnabled(),
            'delay' => $this->ajaxService->getDelay(),
            'nextParams' => $session->getNextSessionParams(),
            'autoTuner' => $this->importService->getAutoTunerMetrics($session->getCurrentLine()),
        ]);

        // Add the AJAX script if necessary
        if (!$session->isFinished() && !$session->hasError()) {
            if ($this->ajaxService->isAjaxEnabled()) {
                $this->view->assign([
                    'ajaxScript' => $this->ajaxService->createAjaxScript(
                        $session,
                        $this->request->getScriptUri()
                    ),
                ]);
            } else {
                $this->view->assign([
                    'redirectScript' => $this->ajaxService->createRedirectScript(
                        $session,
                        $this->request->getScriptUri()
                    ),
                ]);
            }
        }

        $content = $this->view->render('import');
        $this->response->setContent($content);
    }

    /**
     * Action: AJAX response for import.
     *
     * @return void
     */
    public function ajaxImport(): void
    {
        // Read from session (AJAX requires JavaScript, so session is always available)
        $session = ImportSession::fromSession();

        if (!$session) {
            $this->response
                ->asXml()
                ->setContent('<?xml version="1.0"?><root><error>No active import session</error></root>');
            return;
        }

        // Execute the import session
        $session = $this->importService->executeSession($session);
        $session->toSession(); // Save progress to session

        // If finished or error, return the complete HTML page
        if ($session->isFinished() || $session->hasError()) {
            // Clear session on completion
            ImportSession::clearSession();

            // Prepare the final view
            $this->view->assign([
                'session' => $session,
                'statistics' => $session->getStatistics(),
                'testMode' => $this->config->get('test_mode', false),
                'ajaxEnabled' => false, // No AJAX script for the final page
                'delay' => 0,
                'nextParams' => [],
            ]);

            $content = $this->view->render('import');
            $this->response->asHtml()->setContent($content);
            return;
        }

        // Otherwise, return the XML response
        $xml = $this->ajaxService->createXmlResponse($session);
        $this->response->asXml()->setContent($xml);
    }

    /**
     * Action: SSE stream for import progress.
     *
     * Streams real-time progress events to the client using Server-Sent Events.
     * Unlike AJAX polling, this maintains a single HTTP connection and pushes updates.
     *
     * IMPORTANT: Session handling for SSE:
     * - Read session data BEFORE starting the stream
     * - Release the session lock so reconnections can work
     * - Keep progress in memory, save to session file directly (bypass session_start)
     *
     * @return void
     */
    public function sseImport(): void
    {
        // Read session BEFORE sending any output
        $session = ImportSession::fromSession();

        // Get session file path for direct writing later
        $sessionSavePath = session_save_path();
        $sessionId = session_id();

        // Handle Windows path separator
        $sessionFile = rtrim($sessionSavePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;

        // Debug: log session info to error_log
        error_log("SSE: session_save_path=" . $sessionSavePath);
        error_log("SSE: session_id=" . $sessionId);
        error_log("SSE: sessionFile=" . $sessionFile);
        error_log("SSE: file_exists=" . (file_exists($sessionFile) ? 'yes' : 'no'));

        // Release session lock BEFORE starting SSE stream
        session_write_close();

        // Now we can start SSE (sends headers)
        $sseService = new SseService();
        $sseService->initStream();

        if (!$session) {
            $sseService->sendEvent('error', ['message' => 'No active import session']);
            return;
        }

        // File-aware auto-tuning: analyze file at start, restore on resume
        if ($session->getStartOffset() === 0 && $session->getFileAnalysisData() === null) {
            // Fresh import - analyze file
            $analysis = $this->importService->analyzeFile($session->getFilename());
            if ($analysis !== null) {
                $session->setFileAnalysisData($analysis->toArray());
            }
        } else {
            // Resuming - restore analysis from session
            $this->importService->restoreFileAnalysis($session->getFileAnalysisData() ?? []);
        }

        // Stream loop: execute sessions and send progress events
        while (!$session->isFinished() && !$session->hasError()) {
            // Check if client disconnected
            if (!$sseService->isClientConnected()) {
                // Client disconnected - save state for reconnection
                $this->saveSessionDirect($session, $sessionFile);
                break;
            }

            // Execute one import session (batch)
            $session = $this->importService->executeSession($session);

            // Save progress directly to session file (no session_start needed)
            $this->saveSessionDirect($session, $sessionFile);

            // Get statistics for the event
            $stats = $session->getStatistics();

            // Send progress event
            $sseService->sendEvent('progress', [
                'stats' => $stats,
                'finished' => $session->isFinished(),
                'error' => $session->hasError() ? $session->getError() : null,
            ]);

            // Small delay between batches to allow browser processing
            usleep(50000); // 50ms
        }

        // Clear session on completion
        if ($session->isFinished()) {
            $this->clearSessionDirect($sessionFile);
        }

        // Send final event and log to history
        $stats = $session->getStatistics();
        if ($session->hasError()) {
            // Log failed import to history
            $this->historyService->addEntry(
                $session->getFilename(),
                $stats['queries_done'] ?? 0,
                $stats['lines_done'] ?? 0,
                $stats['bytes_done'] ?? 0,
                false,
                $session->getError(),
                0.0
            );

            $sseService->sendEvent('error', [
                'message' => $session->getError(),
                'stats' => $stats,
            ]);
        } else {
            // Log successful import to history
            $this->historyService->addEntry(
                $session->getFilename(),
                $stats['queries_done'] ?? 0,
                $stats['lines_done'] ?? 0,
                $stats['bytes_done'] ?? 0,
                true,
                null,
                0.0
            );

            $sseService->sendEvent('complete', [
                'stats' => $stats,
            ]);
        }
    }

    /**
     * Save session data directly to session file (bypasses session_start).
     *
     * PHP sessions use a special serialization format: "key|serialized_value"
     * NOT standard serialize() for the whole array.
     *
     * @param ImportSession $session Import session
     * @param string $sessionFile Path to session file
     */
    private function saveSessionDirect(ImportSession $session, string $sessionFile): void
    {
        // Build import data array
        $importData = [
            'filename' => $session->getFilename(),
            'start_line' => $session->getCurrentLine(),
            'offset' => $session->getCurrentOffset(),
            'total_queries' => $session->getTotalQueries(),
            'delimiter' => $session->getDelimiter(),
            'pending_query' => $session->getPendingQuery(),
            'in_string' => $session->getInString(),
            'active_quote' => $session->getActiveQuote(),
            'file_size' => $session->getFileSize(),
            'frozen_lines_total' => $session->getFrozenLinesTotal(),
            'frozen_queries_total' => $session->getFrozenQueriesTotal(),
            'active' => true,
        ];

        // PHP session format: "varname|serialized_value"
        $sessionData = 'import|' . serialize($importData);

        // Write to session file
        $result = file_put_contents($sessionFile, $sessionData, LOCK_EX);

        // Debug log
        error_log("SSE: Saved session - line=" . $importData['start_line'] . ", offset=" . $importData['offset'] . ", result=" . ($result !== false ? $result . ' bytes' : 'FAILED'));
    }

    /**
     * Clear session import data directly.
     *
     * @param string $sessionFile Path to session file
     */
    private function clearSessionDirect(string $sessionFile): void
    {
        // Just empty the file (removes all session data)
        if (file_exists($sessionFile)) {
            file_put_contents($sessionFile, '', LOCK_EX);
        }
    }

    /**
     * Stops the current import by clearing the session.
     *
     * @return void
     */
    public function stopImport(): void
    {
        // Clear PHP session import data
        ImportSession::clearSession();

        // Also clear direct session file if it exists (with error suppression for Windows)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionFile = session_save_path() . '/sess_' . session_id();
        if (file_exists($sessionFile) && is_readable($sessionFile)) {
            try {
                // Re-read, remove bigdump keys, re-write
                $data = @file_get_contents($sessionFile);
                if ($data !== false) {
                    // Remove bigdump_import section from serialized session
                    $data = preg_replace('/bigdump_import\|[^}]+\}/', '', $data);
                    @file_put_contents($sessionFile, $data, LOCK_EX);
                }
            } catch (\Throwable $e) {
                // Ignore session file manipulation errors - ImportSession::clearSession() already did the work
            }
        }

        // Redirect to home page
        $scriptUri = $this->request->getScriptUri();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = ($scriptUri === '' || $scriptUri === '/') ? '/' : $scriptUri;
        $this->response->redirect($protocol . '://' . $host . $path);
    }

    /**
     * Drops a table and restarts the import.
     *
     * Used when import fails with "Table already exists" error.
     *
     * @return void
     */
    public function dropRestart(): void
    {
        $tableName = $this->request->input('table', '');
        $filename = $this->request->input('fn', '');

        // Validate table name (security: only allow valid MySQL identifiers)
        if (empty($tableName) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            $this->view->assign(['error' => 'Invalid table name specified']);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Validate filename
        if (empty($filename)) {
            $this->view->assign(['error' => 'No filename specified']);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Check file exists
        $fileHandler = $this->importService->getFileHandler();
        if (!$fileHandler->exists($filename)) {
            $this->view->assign(['error' => "File not found: {$filename}"]);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        try {
            // Connect to database and drop the table
            $db = $this->importService->getDatabase();
            $db->connect();

            // Use backticks to safely quote the table name
            $dropQuery = "DROP TABLE IF EXISTS `{$tableName}`";
            $db->query($dropQuery);

        } catch (\Exception $e) {
            $this->view->assign(['error' => 'Failed to drop table: ' . $e->getMessage()]);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Clear any previous session and create new one
        ImportSession::clearSession();
        $session = ImportSession::fromRequest(
            $filename,
            1,
            0,
            0,
            $this->config->get('delimiter', ';'),
            '',
            false,
            ''
        );
        $session->toSession();

        // Redirect to import page
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUri = $this->request->getScriptUri();
        // Remove /import/drop-restart suffix to get base path
        $baseUri = preg_replace('#/import/drop-restart$#', '', $baseUri);
        $path = ($baseUri === '' || $baseUri === '/') ? '/import' : $baseUri . '/import';

        $this->response->redirect($protocol . '://' . $host . $path);
    }

    /**
     * Preview SQL file content (first N lines/queries).
     *
     * Returns JSON with preview data for modal display.
     *
     * @return void
     */
    public function preview(): void
    {
        // Set JSON content type
        header('Content-Type: application/json; charset=utf-8');

        $filename = $this->request->get('fn', '');
        $maxLines = min((int) $this->request->get('lines', 50), 200); // Cap at 200 lines

        if (empty($filename)) {
            echo json_encode(['error' => 'No filename provided']);
            exit;
        }

        $uploadDir = rtrim($this->config->get('upload_dir', 'uploads/'), '/') . '/';
        $filepath = $uploadDir . basename($filename);

        if (!file_exists($filepath)) {
            echo json_encode(['error' => 'File not found']);
            exit;
        }

        // Detect gzip
        $isGzip = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'gz';

        try {
            $lines = [];
            $queries = [];
            $currentQuery = '';
            $lineCount = 0;
            $queryCount = 0;
            $fileSize = filesize($filepath);

            // Open file (gzip or regular)
            if ($isGzip && function_exists('gzopen')) {
                $handle = gzopen($filepath, 'r');
            } else {
                $handle = fopen($filepath, 'r');
            }

            if (!$handle) {
                echo json_encode(['error' => 'Cannot open file']);
                exit;
            }

            // Skip BOM if present
            $firstBytes = $isGzip ? gzread($handle, 3) : fread($handle, 3);
            if ($firstBytes !== "\xEF\xBB\xBF") {
                // Not a BOM, seek back
                if ($isGzip) {
                    gzrewind($handle);
                } else {
                    rewind($handle);
                }
            }

            // Read lines
            while ($lineCount < $maxLines) {
                $line = $isGzip ? gzgets($handle) : fgets($handle);
                if ($line === false) {
                    break;
                }

                $lineCount++;
                $trimmedLine = trim($line);
                $lines[] = $line;

                // Skip empty lines and comments for query extraction
                if (empty($trimmedLine) || str_starts_with($trimmedLine, '--') || str_starts_with($trimmedLine, '#') || str_starts_with($trimmedLine, '/*')) {
                    continue;
                }

                // Accumulate query
                $currentQuery .= $line;

                // Check if query is complete (ends with ;)
                if (str_ends_with($trimmedLine, ';')) {
                    $queries[] = trim($currentQuery);
                    $currentQuery = '';
                    $queryCount++;

                    // Stop if we have enough queries
                    if ($queryCount >= 10) {
                        break;
                    }
                }
            }

            // Close file
            if ($isGzip) {
                gzclose($handle);
            } else {
                fclose($handle);
            }

            // Prepare response
            $response = [
                'success' => true,
                'filename' => $filename,
                'fileSize' => $fileSize,
                'fileSizeFormatted' => $this->formatBytes($fileSize),
                'isGzip' => $isGzip,
                'linesPreview' => $lineCount,
                'queriesPreview' => count($queries),
                'rawContent' => implode('', $lines),
                'queries' => array_slice($queries, 0, 10), // First 10 queries
            ];

            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Error reading file: ' . $e->getMessage()]);
        }

        exit;
    }

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes Bytes count
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
     * Action: Get import history.
     *
     * Returns JSON with import history and statistics.
     *
     * @return void
     */
    public function history(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $this->request->get('do', 'list');

        switch ($action) {
            case 'clear':
                $this->historyService->clearHistory();
                echo json_encode(['success' => true, 'message' => 'History cleared']);
                break;

            case 'delete':
                $id = $this->request->get('id', '');
                if (empty($id)) {
                    echo json_encode(['success' => false, 'error' => 'No ID provided']);
                } else {
                    $deleted = $this->historyService->deleteEntry($id);
                    echo json_encode(['success' => $deleted]);
                }
                break;

            case 'stats':
                echo json_encode([
                    'success' => true,
                    'statistics' => $this->historyService->getStatistics(),
                ]);
                break;

            case 'list':
            default:
                $limit = (int) $this->request->get('limit', 20);
                echo json_encode([
                    'success' => true,
                    'history' => $this->historyService->getHistory($limit),
                    'statistics' => $this->historyService->getStatistics(),
                ]);
                break;
        }

        exit;
    }

    /**
     * Action: Get files list as JSON for real-time updates.
     *
     * Returns the list of files in the upload directory for AJAX polling.
     *
     * @return void
     */
    public function filesList(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $fileHandler = $this->importService->getFileHandler();
        $files = $fileHandler->listFiles();

        // Transform files for JSON response
        $fileList = array_map(function ($file) {
            return [
                'name' => $file['name'],
                'size' => $file['size'],
                'sizeFormatted' => $this->formatBytes($file['size']),
                'date' => $file['date'],
                'type' => $file['type'],
            ];
        }, $files);

        echo json_encode([
            'success' => true,
            'files' => $fileList,
            'count' => count($fileList),
            'timestamp' => time(),
        ]);

        exit;
    }
}
