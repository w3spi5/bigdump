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
 * Classe BigDumpController - Contrôleur principal
 *
 * Ce contrôleur gère toutes les actions de l'application:
 * - Affichage de la page d'accueil avec liste des fichiers
 * - Upload de fichiers
 * - Suppression de fichiers
 * - Exécution des sessions d'import
 * - Réponses AJAX
 *
 * @package BigDump\Controllers
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class BigDumpController
{
    /**
     * Configuration
     * @var Config
     */
    protected Config $config;

    /**
     * Requête
     * @var Request
     */
    protected Request $request;

    /**
     * Réponse
     * @var Response
     */
    protected Response $response;

    /**
     * Vue
     * @var View
     */
    protected View $view;

    /**
     * Service d'import
     * @var ImportService
     */
    protected ImportService $importService;

    /**
     * Service AJAX
     * @var AjaxService
     */
    protected AjaxService $ajaxService;

    /**
     * Constructeur
     *
     * @param Config $config Configuration
     * @param Request $request Requête
     * @param Response $response Réponse
     * @param View $view Vue
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
     * Action: Page d'accueil
     *
     * Affiche la liste des fichiers disponibles et le formulaire d'upload.
     *
     * @return void
     */
    public function index(): void
    {
        $fileHandler = $this->importService->getFileHandler();

        // Vérifier la configuration de la base de données
        $dbConfigured = $this->importService->isDatabaseConfigured();
        $connectionInfo = null;

        if ($dbConfigured) {
            $connectionInfo = $this->importService->testConnection();
        }

        // Récupérer la liste des fichiers
        $files = $fileHandler->listFiles();

        // Vérifier si le répertoire est accessible en écriture
        $uploadEnabled = $fileHandler->isUploadDirWritable();

        // Taille maximale d'upload
        $uploadMaxSize = $this->config->getUploadMaxFilesize();

        // Nom de fichier prédéfini
        $predefinedFile = $this->config->get('filename', '');

        // Préparer les données pour la vue
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

        // Rendre la vue
        $content = $this->view->render('home');
        $this->response->setContent($content);
    }

    /**
     * Action: Upload de fichier
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

        // Préparer les données pour la vue
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
     * Action: Suppression de fichier
     *
     * @return void
     */
    public function delete(): void
    {
        $fileHandler = $this->importService->getFileHandler();
        $filename = $this->request->input('delete', '');

        $result = ['success' => false, 'message' => 'No file specified'];

        if (!empty($filename)) {
            // Vérifier que ce n'est pas le script lui-même
            if (basename($filename) === $this->request->getScriptName()) {
                $result = ['success' => false, 'message' => 'Cannot delete the script itself'];
            } else {
                $result = $fileHandler->delete($filename);
            }
        }

        // Préparer les données pour la vue
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
     * Action: Démarrage d'import (première page)
     *
     * @return void
     */
    public function startImport(): void
    {
        $filename = $this->request->input('fn', '');

        if (empty($filename)) {
            $filename = $this->config->get('filename', '');
        }

        // Vérifier que le fichier existe
        $fileHandler = $this->importService->getFileHandler();

        if (!$fileHandler->exists($filename)) {
            $this->view->assign([
                'error' => "File not found: {$filename}",
            ]);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Rediriger vers l'import
        $url = $this->request->getScriptUri() . '?' . http_build_query([
            'start' => 1,
            'fn' => $filename,
            'foffset' => 0,
            'totalqueries' => 0,
            'delimiter' => $this->config->get('delimiter', ';'),
        ]);

        $this->response->redirect($url);
    }

    /**
     * Action: Exécution d'une session d'import
     *
     * @return void
     */
    public function import(): void
    {
        // Créer la session d'import
        $session = ImportSession::fromRequest(
            $this->request->input('fn', ''),
            $this->request->getInt('start', 1),
            $this->request->getInt('foffset', 0),
            $this->request->getInt('totalqueries', 0),
            $this->request->input('delimiter', ';')
        );

        // Valider les paramètres
        if (empty($session->getFilename())) {
            $this->view->assign(['error' => 'No filename specified']);
            $content = $this->view->render('error');
            $this->response->setContent($content);
            return;
        }

        // Exécuter la session d'import
        $session = $this->importService->executeSession($session);

        // Préparer les données pour la vue
        $this->view->assign([
            'session' => $session,
            'statistics' => $session->getStatistics(),
            'testMode' => $this->config->get('test_mode', false),
            'ajaxEnabled' => $this->ajaxService->isAjaxEnabled(),
            'delay' => $this->ajaxService->getDelay(),
            'nextParams' => $session->getNextSessionParams(),
        ]);

        // Ajouter le script AJAX si nécessaire
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
     * Action: Réponse AJAX pour import
     *
     * @return void
     */
    public function ajaxImport(): void
    {
        // Créer la session d'import
        $session = ImportSession::fromRequest(
            $this->request->input('fn', ''),
            $this->request->getInt('start', 1),
            $this->request->getInt('foffset', 0),
            $this->request->getInt('totalqueries', 0),
            $this->request->input('delimiter', ';')
        );

        // Valider les paramètres
        if (empty($session->getFilename())) {
            $this->response
                ->asXml()
                ->setContent('<?xml version="1.0"?><root><error>No filename</error></root>');
            return;
        }

        // Exécuter la session d'import
        $session = $this->importService->executeSession($session);

        // Si terminé ou erreur, renvoyer la page HTML complète
        if ($session->isFinished() || $session->hasError()) {
            // Préparer la vue finale
            $this->view->assign([
                'session' => $session,
                'statistics' => $session->getStatistics(),
                'testMode' => $this->config->get('test_mode', false),
                'ajaxEnabled' => false, // Pas de script AJAX pour la page finale
                'delay' => 0,
                'nextParams' => [],
            ]);

            $content = $this->view->render('import');
            $this->response->asHtml()->setContent($content);
            return;
        }

        // Sinon, renvoyer la réponse XML
        $xml = $this->ajaxService->createXmlResponse($session);
        $this->response->asXml()->setContent($xml);
    }
}
