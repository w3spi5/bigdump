<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;
use RuntimeException;

/**
 * Classe FileHandler - Gestionnaire de fichiers
 *
 * Cette classe gère les opérations sur les fichiers dump:
 * - Listing des fichiers disponibles
 * - Upload de fichiers
 * - Suppression de fichiers
 * - Lecture de fichiers (normaux et gzippés)
 *
 * Corrections par rapport à l'original:
 * - Protection contre les attaques path traversal
 * - Meilleure gestion des fichiers gzippés
 * - Gestion correcte du BOM UTF-8, UTF-16, UTF-32
 *
 * @package BigDump\Models
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class FileHandler
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Répertoire d'upload
     * @var string
     */
    private string $uploadDir;

    /**
     * Fichier actuellement ouvert
     * @var resource|null
     */
    private $fileHandle = null;

    /**
     * Mode gzip
     * @var bool
     */
    private bool $gzipMode = false;

    /**
     * Taille du fichier
     * @var int
     */
    private int $fileSize = 0;

    /**
     * Nom du fichier courant
     * @var string
     */
    private string $currentFilename = '';

    /**
     * Constructeur
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->uploadDir = $config->getUploadDir();
        $this->ensureUploadDir();
    }

    /**
     * S'assure que le répertoire d'upload existe
     *
     * @return void
     */
    private function ensureUploadDir(): void
    {
        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Liste les fichiers dump disponibles
     *
     * @return array<int, array{name: string, size: int, date: string, type: string, path: string}> Liste des fichiers
     */
    public function listFiles(): array
    {
        $files = [];

        if (!is_dir($this->uploadDir) || !is_readable($this->uploadDir)) {
            return $files;
        }

        $handle = opendir($this->uploadDir);

        if ($handle === false) {
            return $files;
        }

        while (($filename = readdir($handle)) !== false) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $filepath = $this->uploadDir . '/' . $filename;

            if (!is_file($filepath)) {
                continue;
            }

            $extension = $this->getExtension($filename);

            if (!$this->config->isExtensionAllowed($extension)) {
                continue;
            }

            $files[] = [
                'name' => $filename,
                'size' => filesize($filepath) ?: 0,
                'date' => date('Y-m-d H:i:s', filemtime($filepath) ?: 0),
                'type' => $this->getFileType($extension),
                'path' => $filepath,
            ];
        }

        closedir($handle);

        // Trier par nom
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * Récupère l'extension d'un fichier
     *
     * @param string $filename Nom du fichier
     * @return string Extension (en minuscule, sans point)
     */
    public function getExtension(string $filename): string
    {
        $pos = strrpos($filename, '.');

        if ($pos === false) {
            return '';
        }

        return strtolower(substr($filename, $pos + 1));
    }

    /**
     * Récupère le type de fichier
     *
     * @param string $extension Extension
     * @return string Type de fichier
     */
    private function getFileType(string $extension): string
    {
        return match ($extension) {
            'sql' => 'SQL',
            'gz' => 'GZip',
            'csv' => 'CSV',
            default => 'Unknown',
        };
    }

    /**
     * Upload un fichier
     *
     * @param array{tmp_name: string, name: string, error: int} $file Données du fichier uploadé
     * @return array{success: bool, message: string, filename: string} Résultat de l'upload
     */
    public function upload(array $file): array
    {
        // Vérifier les erreurs d'upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'No file uploaded or upload failed',
                'filename' => '',
            ];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => $this->getUploadErrorMessage($file['error']),
                'filename' => '',
            ];
        }

        // Nettoyer le nom de fichier
        $originalName = $file['name'] ?? 'unknown';
        $cleanName = $this->sanitizeFilename($originalName);

        // Vérifier l'extension
        $extension = $this->getExtension($cleanName);

        if (!$this->config->isExtensionAllowed($extension)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Only .sql, .gz and .csv files are accepted.',
                'filename' => '',
            ];
        }

        // Chemin de destination
        $destPath = $this->uploadDir . '/' . $cleanName;

        // Vérifier si le fichier existe déjà
        if (file_exists($destPath)) {
            return [
                'success' => false,
                'message' => "File '{$cleanName}' already exists. Delete it first or rename your file.",
                'filename' => '',
            ];
        }

        // Déplacer le fichier
        if (!@move_uploaded_file($file['tmp_name'], $destPath)) {
            return [
                'success' => false,
                'message' => "Failed to save file. Check directory permissions for '{$this->uploadDir}'.",
                'filename' => '',
            ];
        }

        return [
            'success' => true,
            'message' => "File '{$cleanName}' uploaded successfully.",
            'filename' => $cleanName,
        ];
    }

    /**
     * Nettoie un nom de fichier
     *
     * Protège contre les attaques path traversal et supprime
     * les caractères dangereux tout en préservant les caractères UTF-8.
     *
     * @param string $filename Nom de fichier original
     * @return string Nom de fichier nettoyé
     */
    public function sanitizeFilename(string $filename): string
    {
        // Supprimer le chemin (protection path traversal)
        $filename = basename($filename);

        // Remplacer les espaces par des underscores
        $filename = str_replace(' ', '_', $filename);

        // Supprimer les caractères dangereux mais garder les caractères UTF-8 valides
        // On garde: lettres, chiffres, tirets, underscores, points
        $filename = preg_replace('/[^\p{L}\p{N}\-_\.]/u', '', $filename) ?? '';

        // Supprimer les séquences de points multiples (protection path traversal)
        $filename = preg_replace('/\.{2,}/', '.', $filename) ?? '';

        // Limiter la longueur (max 255 caractères)
        if (strlen($filename) > 255) {
            $ext = $this->getExtension($filename);

            if (!empty($ext)) {
                // Calculate max base length: 255 - dot (1) - extension length
                $maxBaseLength = 255 - 1 - strlen($ext);
                // Find the dot position to get the base name
                $dotPos = strrpos($filename, '.');
                $base = substr($filename, 0, min($dotPos, $maxBaseLength));
                $filename = $base . '.' . $ext;
            } else {
                // No extension, just truncate
                $filename = substr($filename, 0, 255);
            }
        }

        // Si le nom est vide après nettoyage, générer un nom
        if (empty($filename) || $filename === '.') {
            $filename = 'upload_' . time() . '.sql';
        }

        return $filename;
    }

    /**
     * Récupère le message d'erreur pour un code d'erreur upload
     *
     * @param int $errorCode Code d'erreur
     * @return string Message d'erreur
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in the form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error',
        };
    }

    /**
     * Supprime un fichier
     *
     * @param string $filename Nom du fichier
     * @return array{success: bool, message: string} Résultat de la suppression
     */
    public function delete(string $filename): array
    {
        // Nettoyer le nom (protection path traversal)
        $cleanName = basename($filename);

        // Vérifier l'extension
        $extension = $this->getExtension($cleanName);

        if (!$this->config->isExtensionAllowed($extension)) {
            return [
                'success' => false,
                'message' => 'Cannot delete this file type',
            ];
        }

        $filepath = $this->uploadDir . '/' . $cleanName;

        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'message' => "File '{$cleanName}' not found",
            ];
        }

        if (!@unlink($filepath)) {
            return [
                'success' => false,
                'message' => "Failed to delete '{$cleanName}'. Check permissions.",
            ];
        }

        return [
            'success' => true,
            'message' => "File '{$cleanName}' deleted successfully",
        ];
    }

    /**
     * Ouvre un fichier pour lecture
     *
     * @param string $filename Nom du fichier
     * @return bool True si l'ouverture réussit
     * @throws RuntimeException Si le fichier ne peut pas être ouvert
     */
    public function open(string $filename): bool
    {
        $this->close();

        // Nettoyer le nom (protection path traversal)
        $cleanName = basename($filename);

        // Vérifier l'extension
        $extension = $this->getExtension($cleanName);

        if (!$this->config->isExtensionAllowed($extension)) {
            throw new RuntimeException("File type not allowed: {$extension}");
        }

        $filepath = $this->uploadDir . '/' . $cleanName;

        if (!file_exists($filepath)) {
            throw new RuntimeException("File not found: {$cleanName}");
        }

        if (!is_readable($filepath)) {
            throw new RuntimeException("File not readable: {$cleanName}");
        }

        $this->gzipMode = ($extension === 'gz');

        if ($this->gzipMode) {
            if (!function_exists('gzopen')) {
                throw new RuntimeException('GZip support not available in PHP');
            }
            $this->fileHandle = @gzopen($filepath, 'rb');
        } else {
            $this->fileHandle = @fopen($filepath, 'rb');
        }

        if ($this->fileHandle === false) {
            $this->fileHandle = null;
            throw new RuntimeException("Cannot open file: {$cleanName}");
        }

        $this->currentFilename = $cleanName;

        // Déterminer la taille du fichier
        if (!$this->gzipMode) {
            $this->fileSize = filesize($filepath) ?: 0;
        } else {
            // Pour les fichiers gzip, on ne peut pas connaître la taille non compressée
            // sans lire tout le fichier, donc on laisse à 0
            $this->fileSize = 0;
        }

        return true;
    }

    /**
     * Positionne le pointeur de fichier
     *
     * @param int $offset Position en octets
     * @return bool True si le seek réussit
     */
    public function seek(int $offset): bool
    {
        if ($this->fileHandle === null) {
            return false;
        }

        if ($this->gzipMode) {
            return @gzseek($this->fileHandle, $offset) === 0;
        }

        return @fseek($this->fileHandle, $offset) === 0;
    }

    /**
     * Récupère la position actuelle du pointeur
     *
     * @return int Position en octets
     */
    public function tell(): int
    {
        if ($this->fileHandle === null) {
            return 0;
        }

        if ($this->gzipMode) {
            return @gztell($this->fileHandle) ?: 0;
        }

        return @ftell($this->fileHandle) ?: 0;
    }

    /**
     * Lit une ligne du fichier
     *
     * @return string|false Ligne lue ou false si fin de fichier
     */
    public function readLine(): string|false
    {
        if ($this->fileHandle === null) {
            return false;
        }

        $chunkLength = $this->config->get('data_chunk_length', 16384);
        $line = '';

        while (!$this->eof()) {
            if ($this->gzipMode) {
                $chunk = @gzgets($this->fileHandle, $chunkLength);
            } else {
                $chunk = @fgets($this->fileHandle, $chunkLength);
            }

            if ($chunk === false) {
                break;
            }

            $line .= $chunk;

            // Vérifier si on a atteint la fin de la ligne
            $lastChar = substr($line, -1);
            if ($lastChar === "\n" || $lastChar === "\r") {
                break;
            }
        }

        if ($line === '') {
            return false;
        }

        return $line;
    }

    /**
     * Vérifie si on est à la fin du fichier
     *
     * @return bool True si fin de fichier
     */
    public function eof(): bool
    {
        if ($this->fileHandle === null) {
            return true;
        }

        if ($this->gzipMode) {
            return @gzeof($this->fileHandle);
        }

        return @feof($this->fileHandle);
    }

    /**
     * Ferme le fichier
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->fileHandle !== null) {
            if ($this->gzipMode) {
                @gzclose($this->fileHandle);
            } else {
                @fclose($this->fileHandle);
            }
            $this->fileHandle = null;
        }

        $this->gzipMode = false;
        $this->fileSize = 0;
        $this->currentFilename = '';
    }

    /**
     * Supprime le BOM (Byte Order Mark) d'une chaîne
     *
     * Gère UTF-8, UTF-16 LE/BE, UTF-32 LE/BE
     *
     * @param string $string Chaîne à nettoyer
     * @return string Chaîne sans BOM
     */
    public function removeBom(string $string): string
    {
        // UTF-8 BOM (EF BB BF)
        if (str_starts_with($string, "\xEF\xBB\xBF")) {
            return substr($string, 3);
        }

        // UTF-32 BE BOM (00 00 FE FF)
        if (str_starts_with($string, "\x00\x00\xFE\xFF")) {
            return substr($string, 4);
        }

        // UTF-32 LE BOM (FF FE 00 00)
        if (str_starts_with($string, "\xFF\xFE\x00\x00")) {
            return substr($string, 4);
        }

        // UTF-16 BE BOM (FE FF)
        if (str_starts_with($string, "\xFE\xFF")) {
            return substr($string, 2);
        }

        // UTF-16 LE BOM (FF FE)
        if (str_starts_with($string, "\xFF\xFE")) {
            return substr($string, 2);
        }

        return $string;
    }

    /**
     * Récupère la taille du fichier
     *
     * @return int Taille en octets (0 pour les fichiers gzip)
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Vérifie si le fichier est en mode gzip
     *
     * @return bool True si mode gzip
     */
    public function isGzipMode(): bool
    {
        return $this->gzipMode;
    }

    /**
     * Récupère le nom du fichier courant
     *
     * @return string Nom du fichier
     */
    public function getCurrentFilename(): string
    {
        return $this->currentFilename;
    }

    /**
     * Vérifie si le répertoire d'upload est accessible en écriture
     *
     * @return bool True si accessible en écriture
     */
    public function isUploadDirWritable(): bool
    {
        if (!is_dir($this->uploadDir)) {
            return false;
        }

        // Tester avec un fichier temporaire
        $testFile = $this->uploadDir . '/.write_test_' . time();
        $handle = @fopen($testFile, 'w');

        if ($handle === false) {
            return false;
        }

        fclose($handle);
        @unlink($testFile);

        return true;
    }

    /**
     * Récupère le répertoire d'upload
     *
     * @return string Chemin du répertoire
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Vérifie si un fichier existe
     *
     * @param string $filename Nom du fichier
     * @return bool True si le fichier existe
     */
    public function exists(string $filename): bool
    {
        $cleanName = basename($filename);
        return file_exists($this->uploadDir . '/' . $cleanName);
    }

    /**
     * Récupère le chemin complet d'un fichier
     *
     * @param string $filename Nom du fichier
     * @return string Chemin complet
     */
    public function getFullPath(string $filename): string
    {
        return $this->uploadDir . '/' . basename($filename);
    }

    /**
     * Destructeur - ferme le fichier
     */
    public function __destruct()
    {
        $this->close();
    }
}
