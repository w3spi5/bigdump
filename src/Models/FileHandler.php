<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;
use RuntimeException;

/**
 * FileHandler Class - File Manager
 *
 * This class handles dump file operations:
 * - Listing available files
 * - Uploading files
 * - Deleting files
 * - Reading files (normal and gzipped)
 *
 * Improvements over original:
 * - Protection against path traversal attacks
 * - Better gzip file handling
 * - Proper BOM handling for UTF-8, UTF-16, UTF-32
 *
 * @package BigDump\Models
 * @author  MVC Refactoring
 * @version 2.2
 */
class FileHandler
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Upload directory
     * @var string
     */
    private string $uploadDir;

    /**
     * Currently open file
     * @var resource|null
     */
    private $fileHandle = null;

    /**
     * Gzip mode
     * @var bool
     */
    private bool $gzipMode = false;

    /**
     * File size
     * @var int
     */
    private int $fileSize = 0;

    /**
     * Current filename
     * @var string
     */
    private string $currentFilename = '';

    /**
     * Constructor
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
     * Ensures upload directory exists
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
     * Lists available dump files
     *
     * @return array<int, array{name: string, size: int, date: string, type: string, path: string}> List of files
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

        // Sort by name
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * Retrieves file extension
     *
     * @param string $filename Filename
     * @return string Extension (lowercase, without dot)
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
     * Retrieves file type
     *
     * @param string $extension Extension
     * @return string File type
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
     * Uploads a file
     *
     * @param array{tmp_name: string, name: string, error: int} $file Uploaded file data
     * @return array{success: bool, message: string, filename: string} Upload result
     */
    public function upload(array $file): array
    {
        // Check upload errors
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

        // Clean filename
        $originalName = $file['name'] ?? 'unknown';
        $cleanName = $this->sanitizeFilename($originalName);

        // Check extension
        $extension = $this->getExtension($cleanName);

        if (!$this->config->isExtensionAllowed($extension)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Only .sql, .gz and .csv files are accepted.',
                'filename' => '',
            ];
        }

        // Destination path
        $destPath = $this->uploadDir . '/' . $cleanName;

        // Check if file already exists
        if (file_exists($destPath)) {
            return [
                'success' => false,
                'message' => "File '{$cleanName}' already exists. Delete it first or rename your file.",
                'filename' => '',
            ];
        }

        // Move file
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
     * Sanitizes a filename
     *
     * Protects against path traversal attacks and removes
     * dangerous characters while preserving UTF-8 characters.
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove path (path traversal protection)
        $filename = basename($filename);

        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);

        // Remove dangerous characters but keep valid UTF-8 characters
        // Keep: letters, digits, hyphens, underscores, dots
        $filename = preg_replace('/[^\p{L}\p{N}\-_\.]/u', '', $filename) ?? '';

        // Remove multiple consecutive dots (path traversal protection)
        $filename = preg_replace('/\.{2,}/', '.', $filename) ?? '';

        // Limit length (max 255 characters)
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

        // If name is empty after sanitization, generate a name
        if (empty($filename) || $filename === '.') {
            $filename = 'upload_' . time() . '.sql';
        }

        return $filename;
    }

    /**
     * Retrieves error message for upload error code
     *
     * @param int $errorCode Error code
     * @return string Error message
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
     * Deletes a file
     *
     * @param string $filename Filename
     * @return array{success: bool, message: string} Deletion result
     */
    public function delete(string $filename): array
    {
        // Clean name (path traversal protection)
        $cleanName = basename($filename);

        // Check extension
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
     * Opens a file for reading
     *
     * @param string $filename Filename
     * @return bool True if opening succeeds
     * @throws RuntimeException If file cannot be opened
     */
    public function open(string $filename): bool
    {
        $this->close();

        // Clean name (path traversal protection)
        $cleanName = basename($filename);

        // Check extension
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

        // Determine file size
        if (!$this->gzipMode) {
            $this->fileSize = filesize($filepath) ?: 0;
        } else {
            // For gzip files, we cannot know the uncompressed size
            // without reading the entire file, so leave it at 0
            $this->fileSize = 0;
        }

        return true;
    }

    /**
     * Sets file pointer position
     *
     * @param int $offset Position in bytes
     * @return bool True if seek succeeds
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
     * Retrieves current pointer position
     *
     * @return int Position in bytes
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
     * Reads a line from file
     *
     * @return string|false Read line or false if end of file
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

            // Check if end of line reached
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
     * Checks if end of file reached
     *
     * @return bool True if end of file
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
     * Closes the file
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
     * Removes BOM (Byte Order Mark) from a string
     *
     * Handles UTF-8, UTF-16 LE/BE, UTF-32 LE/BE
     *
     * @param string $string String to clean
     * @return string String without BOM
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
     * Retrieves file size
     *
     * @return int Size in bytes (0 for gzip files)
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Checks if file is in gzip mode
     *
     * @return bool True if gzip mode
     */
    public function isGzipMode(): bool
    {
        return $this->gzipMode;
    }

    /**
     * Retrieves current filename
     *
     * @return string Filename
     */
    public function getCurrentFilename(): string
    {
        return $this->currentFilename;
    }

    /**
     * Checks if upload directory is writable
     *
     * @return bool True if writable
     */
    public function isUploadDirWritable(): bool
    {
        if (!is_dir($this->uploadDir)) {
            return false;
        }

        // Test with temporary file
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
     * Retrieves upload directory
     *
     * @return string Directory path
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Checks if a file exists
     *
     * @param string $filename Filename
     * @return bool True if file exists
     */
    public function exists(string $filename): bool
    {
        $cleanName = basename($filename);
        return file_exists($this->uploadDir . '/' . $cleanName);
    }

    /**
     * Retrieves full path of a file
     *
     * @param string $filename Filename
     * @return string Full path
     */
    public function getFullPath(string $filename): string
    {
        return $this->uploadDir . '/' . basename($filename);
    }

    /**
     * Destructor - closes the file
     */
    public function __destruct()
    {
        $this->close();
    }
}
