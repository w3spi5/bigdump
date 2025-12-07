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

/**
 * BigDumpController Class - Main controller.
 *
 * This controller handles all application actions:
 * - Displaying the home page with file list
 * - File upload
 * - File deletion
 * - Executing import sessions
 * - AJAX responses
 *
 * @package BigDump\Controllers
 * @author  Refactorisation MVC
 * @version 2.5
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

        // Clear any pending query file from previous import of this file
        $uploadsDir = $this->config->getUploadDir();
        $pendingFile = $uploadsDir . '/.pending_' . md5($filename) . '.tmp';
        if (file_exists($pendingFile)) {
            @unlink($pendingFile);
        }

        // Redirect to import
        $url = $this->request->getScriptUri() . '?' . http_build_query([
            'start' => 1,
            'fn' => $filename,
            'foffset' => 0,
            'totalqueries' => 0,
            'delimiter' => $this->config->get('delimiter', ';'),
            'instring' => '0',
        ]);

        $this->response->redirect($url);
    }

    /**
     * Action: Executing an import session.
     *
     * @return void
     */
    public function import(): void
    {
        // Create the import session (pendingQuery stored in PHP session, not URL)
        $session = ImportSession::fromRequest(
            $this->request->input('fn', ''),
            $this->request->getInt('start', 1),
            $this->request->getInt('foffset', 0),
            $this->request->getInt('totalqueries', 0),
            $this->request->input('delimiter', ';'),
            '', // pendingQuery now in $_SESSION
            $this->request->getInt('instring', 0) === 1,
            $this->request->input('activequote', '')
        );

        // Validate parameters
        if (empty($session->getFilename())) {
            $this->view->assign(['error' => 'No filename specified']);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Execute the import session
        $session = $this->importService->executeSession($session);

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
        // Create the import session (pendingQuery stored in PHP session, not URL)
        $session = ImportSession::fromRequest(
            $this->request->input('fn', ''),
            $this->request->getInt('start', 1),
            $this->request->getInt('foffset', 0),
            $this->request->getInt('totalqueries', 0),
            $this->request->input('delimiter', ';'),
            '', // pendingQuery now in $_SESSION
            $this->request->getInt('instring', 0) === 1,
            $this->request->input('activequote', '')
        );

        // Validate parameters
        if (empty($session->getFilename())) {
            $this->response
                ->asXml()
                ->setContent('<?xml version="1.0"?><root><error>No filename</error></root>');
            return;
        }

        // Execute the import session
        $session = $this->importService->executeSession($session);

        // If finished or error, return the complete HTML page
        if ($session->isFinished() || $session->hasError()) {
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
}
