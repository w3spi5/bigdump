<?php

declare(strict_types=1);

namespace BigDump\Models;

/**
 * Classe ImportSession - Gestion de l'état d'une session d'import
 *
 * Cette classe encapsule toutes les données d'une session d'import,
 * incluant les statistiques et l'état de progression.
 *
 * @package BigDump\Models
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class ImportSession
{
    /**
     * Nom du fichier en cours d'import
     * @var string
     */
    private string $filename = '';

    /**
     * Numéro de ligne de départ
     * @var int
     */
    private int $startLine = 1;

    /**
     * Offset dans le fichier au départ
     * @var int
     */
    private int $startOffset = 0;

    /**
     * Numéro de ligne actuel
     * @var int
     */
    private int $currentLine = 1;

    /**
     * Offset actuel dans le fichier
     * @var int
     */
    private int $currentOffset = 0;

    /**
     * Nombre de requêtes exécutées dans cette session
     * @var int
     */
    private int $sessionQueries = 0;

    /**
     * Nombre total de requêtes exécutées
     * @var int
     */
    private int $totalQueries = 0;

    /**
     * Taille totale du fichier
     * @var int
     */
    private int $fileSize = 0;

    /**
     * Délimiteur SQL actuel
     * @var string
     */
    private string $delimiter = ';';

    /**
     * Import terminé
     * @var bool
     */
    private bool $finished = false;

    /**
     * Erreur rencontrée
     * @var string|null
     */
    private ?string $error = null;

    /**
     * Mode gzip
     * @var bool
     */
    private bool $gzipMode = false;

    /**
     * Crée une nouvelle session à partir des paramètres de requête
     *
     * @param string $filename Nom du fichier
     * @param int $startLine Ligne de départ
     * @param int $startOffset Offset de départ
     * @param int $totalQueries Total des requêtes précédentes
     * @param string $delimiter Délimiteur SQL
     * @return self
     */
    public static function fromRequest(
        string $filename,
        int $startLine = 1,
        int $startOffset = 0,
        int $totalQueries = 0,
        string $delimiter = ';'
    ): self {
        $session = new self();
        $session->filename = $filename;
        $session->startLine = max(1, $startLine);
        $session->currentLine = $session->startLine;
        $session->startOffset = max(0, $startOffset);
        $session->currentOffset = $session->startOffset;
        $session->totalQueries = max(0, $totalQueries);
        $session->delimiter = $delimiter;

        return $session;
    }

    /**
     * Récupère le nom du fichier
     *
     * @return string Nom du fichier
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Définit le nom du fichier
     *
     * @param string $filename Nom du fichier
     * @return self
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Récupère la ligne de départ
     *
     * @return int Ligne de départ
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * Récupère la ligne actuelle
     *
     * @return int Ligne actuelle
     */
    public function getCurrentLine(): int
    {
        return $this->currentLine;
    }

    /**
     * Incrémente le numéro de ligne
     *
     * @return self
     */
    public function incrementLine(): self
    {
        $this->currentLine++;
        return $this;
    }

    /**
     * Récupère l'offset de départ
     *
     * @return int Offset de départ
     */
    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    /**
     * Récupère l'offset actuel
     *
     * @return int Offset actuel
     */
    public function getCurrentOffset(): int
    {
        return $this->currentOffset;
    }

    /**
     * Définit l'offset actuel
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
     * Récupère le nombre de requêtes de la session
     *
     * @return int Nombre de requêtes
     */
    public function getSessionQueries(): int
    {
        return $this->sessionQueries;
    }

    /**
     * Incrémente le compteur de requêtes
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
     * Récupère le nombre total de requêtes
     *
     * @return int Total des requêtes
     */
    public function getTotalQueries(): int
    {
        return $this->totalQueries;
    }

    /**
     * Récupère la taille du fichier
     *
     * @return int Taille en octets
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Définit la taille du fichier
     *
     * @param int $size Taille en octets
     * @return self
     */
    public function setFileSize(int $size): self
    {
        $this->fileSize = $size;
        return $this;
    }

    /**
     * Récupère le délimiteur SQL
     *
     * @return string Délimiteur
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Définit le délimiteur SQL
     *
     * @param string $delimiter Délimiteur
     * @return self
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Vérifie si l'import est terminé
     *
     * @return bool True si terminé
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }

    /**
     * Marque l'import comme terminé
     *
     * @return self
     */
    public function setFinished(): self
    {
        $this->finished = true;
        return $this;
    }

    /**
     * Vérifie s'il y a une erreur
     *
     * @return bool True si erreur
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Récupère l'erreur
     *
     * @return string|null Message d'erreur
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Définit une erreur
     *
     * @param string $error Message d'erreur
     * @return self
     */
    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }

    /**
     * Vérifie si le mode gzip est actif
     *
     * @return bool True si mode gzip
     */
    public function isGzipMode(): bool
    {
        return $this->gzipMode;
    }

    /**
     * Définit le mode gzip
     *
     * @param bool $gzipMode Mode gzip
     * @return self
     */
    public function setGzipMode(bool $gzipMode): self
    {
        $this->gzipMode = $gzipMode;
        return $this;
    }

    /**
     * Calcule les statistiques de la session
     *
     * @return array<string, mixed> Statistiques
     */
    public function getStatistics(): array
    {
        $linesThis = $this->currentLine - $this->startLine;
        $linesDone = $this->currentLine - 1;

        $bytesThis = $this->currentOffset - $this->startOffset;
        $bytesDone = $this->currentOffset;

        // Calculs pour fichiers non-gzip uniquement
        $bytesTogo = $this->gzipMode ? null : max(0, $this->fileSize - $this->currentOffset);
        $bytesTotal = $this->gzipMode ? null : $this->fileSize;

        // Pourcentages
        $pctDone = null;
        $pctThis = null;
        $pctTogo = null;

        if (!$this->gzipMode && $this->fileSize > 0) {
            $pctDone = min(100, (int) ceil($this->currentOffset / $this->fileSize * 100));
            $pctThis = min(100, (int) ceil($bytesThis / $this->fileSize * 100));
            $pctTogo = max(0, 100 - $pctDone);
        }

        return [
            // Lignes
            'lines_this' => $linesThis,
            'lines_done' => $linesDone,
            'lines_togo' => $this->finished ? 0 : null,
            'lines_total' => $this->finished ? $linesDone : null,

            // Requêtes
            'queries_this' => $this->sessionQueries,
            'queries_done' => $this->totalQueries,
            'queries_togo' => $this->finished ? 0 : null,
            'queries_total' => $this->finished ? $this->totalQueries : null,

            // Octets
            'bytes_this' => $bytesThis,
            'bytes_done' => $bytesDone,
            'bytes_togo' => $bytesTogo,
            'bytes_total' => $bytesTotal,

            // Kilo-octets
            'kb_this' => round($bytesThis / 1024, 2),
            'kb_done' => round($bytesDone / 1024, 2),
            'kb_togo' => $bytesTogo !== null ? round($bytesTogo / 1024, 2) : null,
            'kb_total' => $bytesTotal !== null ? round($bytesTotal / 1024, 2) : null,

            // Méga-octets
            'mb_this' => round($bytesThis / 1048576, 2),
            'mb_done' => round($bytesDone / 1048576, 2),
            'mb_togo' => $bytesTogo !== null ? round($bytesTogo / 1048576, 2) : null,
            'mb_total' => $bytesTotal !== null ? round($bytesTotal / 1048576, 2) : null,

            // Pourcentages
            'pct_this' => $pctThis,
            'pct_done' => $pctDone,
            'pct_togo' => $pctTogo,
            'pct_total' => 100,

            // État
            'finished' => $this->finished,
            'gzip_mode' => $this->gzipMode,
        ];
    }

    /**
     * Génère les paramètres pour la prochaine session
     *
     * @return array<string, mixed> Paramètres
     */
    public function getNextSessionParams(): array
    {
        return [
            'start' => $this->currentLine,
            'fn' => $this->filename,
            'foffset' => $this->currentOffset,
            'totalqueries' => $this->totalQueries,
            'delimiter' => $this->delimiter,
        ];
    }

    /**
     * Convertit la session en tableau pour XML/JSON
     *
     * @return array<string, mixed> Données de la session
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
