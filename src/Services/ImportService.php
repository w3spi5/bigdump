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
 * Classe ImportService - Service principal d'importation
 *
 * Ce service orchestre l'ensemble du processus d'importation:
 * - Ouverture et lecture du fichier dump
 * - Parsing des requêtes SQL
 * - Exécution des requêtes dans la base de données
 * - Gestion des sessions d'import échelonnées
 *
 * @package BigDump\Services
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class ImportService
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Gestionnaire de base de données
     * @var Database
     */
    private Database $database;

    /**
     * Gestionnaire de fichiers
     * @var FileHandler
     */
    private FileHandler $fileHandler;

    /**
     * Parser SQL
     * @var SqlParser
     */
    private SqlParser $sqlParser;

    /**
     * Nombre de lignes par session
     * @var int
     */
    private int $linesPerSession;

    /**
     * Constructeur
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
     * Exécute une session d'import
     *
     * @param ImportSession $session Session d'import
     * @return ImportSession Session mise à jour
     * @throws RuntimeException En cas d'erreur critique
     */
    public function executeSession(ImportSession $session): ImportSession
    {
        try {
            // Connecter à la base de données
            $this->database->connect();

            // Ouvrir le fichier
            $this->openFile($session);

            // Initialiser le parser avec le délimiteur de la session
            $this->sqlParser->setDelimiter($session->getDelimiter());
            $this->sqlParser->reset();

            // Vider la table CSV si nécessaire
            $this->emptyCsvTableIfNeeded($session);

            // Traiter les lignes
            $this->processLines($session);

            // Mettre à jour l'offset final
            $session->setCurrentOffset($this->fileHandler->tell());

            // Mettre à jour le délimiteur si changé
            $session->setDelimiter($this->sqlParser->getDelimiter());

        } catch (RuntimeException $e) {
            $session->setError($e->getMessage());
        } finally {
            $this->fileHandler->close();
            $this->database->close();
        }

        return $session;
    }

    /**
     * Ouvre le fichier et positionne le curseur
     *
     * @param ImportSession $session Session d'import
     * @return void
     * @throws RuntimeException Si le fichier ne peut pas être ouvert
     */
    private function openFile(ImportSession $session): void
    {
        $filename = $session->getFilename();

        // Vérifier l'extension pour les fichiers CSV
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

        // Ouvrir le fichier
        $this->fileHandler->open($filename);

        // Définir les propriétés de la session
        $session->setFileSize($this->fileHandler->getFileSize());
        $session->setGzipMode($this->fileHandler->isGzipMode());

        // Positionner au bon offset
        $offset = $session->getStartOffset();

        if ($offset > 0) {
            if (!$this->fileHandler->seek($offset)) {
                throw new RuntimeException("Cannot seek to offset {$offset}");
            }
        }
    }

    /**
     * Vide la table CSV si c'est la première session et que l'option est activée
     *
     * @param ImportSession $session Session d'import
     * @return void
     * @throws RuntimeException Si la suppression échoue
     */
    private function emptyCsvTableIfNeeded(ImportSession $session): void
    {
        // Seulement à la première session
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

        // Supprimer les données de la table
        $query = "DELETE FROM `{$safeTable}`";

        if (!$this->database->query($query)) {
            throw new RuntimeException(
                "Failed to empty table '{$safeTable}': " . $this->database->getLastError()
            );
        }
    }

    /**
     * Traite les lignes du fichier
     *
     * @param ImportSession $session Session d'import
     * @return void
     * @throws RuntimeException En cas d'erreur
     */
    private function processLines(ImportSession $session): void
    {
        $startLine = $session->getStartLine();
        $maxLine = $startLine + $this->linesPerSession;
        $isCsv = $this->fileHandler->getExtension($session->getFilename()) === 'csv';
        $csvTable = $this->config->get('csv_insert_table', '');
        $isFirstLine = ($session->getStartOffset() === 0);

        while ($session->getCurrentLine() < $maxLine || $this->sqlParser->isInString()) {
            // Lire une ligne
            $line = $this->fileHandler->readLine();

            if ($line === false) {
                // Fin du fichier
                $this->handleEndOfFile($session);
                break;
            }

            // Supprimer le BOM à la première ligne
            if ($isFirstLine) {
                $line = $this->fileHandler->removeBom($line);
                $isFirstLine = false;
            }

            // Traiter la ligne
            if ($isCsv) {
                $this->processCsvLine($session, $line, $csvTable);
            } else {
                $this->processSqlLine($session, $line);
            }

            $session->incrementLine();
        }
    }

    /**
     * Traite une ligne SQL
     *
     * @param ImportSession $session Session d'import
     * @param string $line Ligne à traiter
     * @return void
     * @throws RuntimeException En cas d'erreur SQL
     */
    private function processSqlLine(ImportSession $session, string $line): void
    {
        $result = $this->sqlParser->parseLine($line);

        // Vérifier les erreurs de parsing
        if ($result['error'] !== null) {
            throw new RuntimeException(
                "Line {$session->getCurrentLine()}: {$result['error']}"
            );
        }

        // Exécuter la requête si complète
        if ($result['query'] !== null) {
            $this->executeQuery($session, $result['query']);
        }
    }

    /**
     * Traite une ligne CSV
     *
     * @param ImportSession $session Session d'import
     * @param string $line Ligne CSV
     * @param string $table Table de destination
     * @return void
     * @throws RuntimeException En cas d'erreur
     */
    private function processCsvLine(ImportSession $session, string $line, string $table): void
    {
        // Ignorer les lignes invalides
        if (!$this->sqlParser->isValidCsvLine($line)) {
            return;
        }

        // Convertir en INSERT
        $query = $this->sqlParser->csvToInsert($line, $table);

        // Exécuter la requête
        $this->executeQuery($session, $query);
    }

    /**
     * Exécute une requête SQL
     *
     * @param ImportSession $session Session d'import
     * @param string $query Requête SQL
     * @return void
     * @throws RuntimeException En cas d'erreur SQL
     */
    private function executeQuery(ImportSession $session, string $query): void
    {
        if (empty(trim($query))) {
            return;
        }

        if (!$this->database->query($query)) {
            $error = $this->database->getLastError();
            $lineNum = $session->getCurrentLine();

            // Tronquer la requête pour l'affichage
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
     * Gère la fin du fichier
     *
     * @param ImportSession $session Session d'import
     * @return void
     * @throws RuntimeException Si une requête est incomplète
     */
    private function handleEndOfFile(ImportSession $session): void
    {
        // Vérifier s'il reste une requête incomplète
        $pendingQuery = $this->sqlParser->getPendingQuery();

        if ($pendingQuery !== null) {
            // Essayer d'exécuter la requête finale
            $this->executeQuery($session, $pendingQuery);
        }

        // Vérifier qu'on n'est pas dans une chaîne non fermée
        if ($this->sqlParser->isInString()) {
            throw new RuntimeException(
                "End of file reached with unclosed string. " .
                "The dump file may be corrupted or truncated."
            );
        }

        $session->setFinished();
    }

    /**
     * Récupère le gestionnaire de base de données
     *
     * @return Database Instance de Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Récupère le gestionnaire de fichiers
     *
     * @return FileHandler Instance de FileHandler
     */
    public function getFileHandler(): FileHandler
    {
        return $this->fileHandler;
    }

    /**
     * Vérifie si la base de données est configurée
     *
     * @return bool True si configurée
     */
    public function isDatabaseConfigured(): bool
    {
        return $this->config->isDatabaseConfigured();
    }

    /**
     * Teste la connexion à la base de données
     *
     * @return array{success: bool, message: string, charset: string} Résultat du test
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
