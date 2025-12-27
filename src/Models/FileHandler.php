<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;
use BigDump\Services\FileAnalysisResult;
use RuntimeException;

/**
 * FileHandler Class - File Manager
 *
 * This class handles dump file operations:
 * - Listing available files
 * - Uploading files
 * - Deleting files
 * - Reading files (normal, gzipped, and bz2 compressed)
 *
 * Improvements over original:
 * - Protection against path traversal attacks
 * - Better gzip file handling
 * - BZ2 compression support with seek workaround (v2.20+)
 * - Proper BOM handling for UTF-8, UTF-16, UTF-32
 * - Configurable buffer size based on performance profile (v2.19+)
 *
 * @package BigDump\Models
 * @author  w3spi5
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
     * BZ2 mode
     * @var bool
     */
    private bool $bz2Mode = false;

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
     * Current file path (needed for BZ2 seek workaround)
     * @var string
     */
    private string $currentFilepath = '';

    /**
     * Internal read buffer for optimized line reading
     * @var string
     */
    private string $readBuffer = '';

    /**
     * Buffer size for chunked reading
     * Configurable via config (default: 64KB conservative, 128KB aggressive)
     * @var int
     */
    private int $bufferSize;

    /**
     * Buffer size constraints
     */
    private const MIN_BUFFER_SIZE = 65536;   // 64KB
    private const MAX_BUFFER_SIZE = 262144;  // 256KB

    /**
     * Buffer size recommendations by file category
     * @var array<string, int>
     */
    private const CATEGORY_BUFFER_SIZES = [
        'tiny'    => 65536,   // 64KB for small files
        'small'   => 65536,   // 64KB for small files
        'medium'  => 98304,   // 96KB for medium files
        'large'   => 131072,  // 128KB for large files
        'massive' => 262144,  // 256KB for massive files
    ];

    /**
     * Constructor
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->uploadDir = $config->getUploadDir();

        // Read configurable buffer size from config (profile-based)
        $configuredBufferSize = (int) $config->get('file_buffer_size', self::MIN_BUFFER_SIZE);

        // Validate and clamp buffer size within allowed range
        $this->bufferSize = $this->clampBufferSize($configuredBufferSize);

        $this->ensureUploadDir();
    }

    /**
     * Clamps buffer size to valid range
     *
     * @param int $bufferSize Requested buffer size
     * @return int Clamped buffer size
     */
    private function clampBufferSize(int $bufferSize): int
    {
        if ($bufferSize < self::MIN_BUFFER_SIZE) {
            return self::MIN_BUFFER_SIZE;
        }

        if ($bufferSize > self::MAX_BUFFER_SIZE) {
            return self::MAX_BUFFER_SIZE;
        }

        return $bufferSize;
    }

    /**
     * Sets buffer size based on file category for optimal performance
     *
     * Call this method after file analysis to optimize buffer size
     * for the specific file being imported.
     *
     * @param string $category File category from FileAnalysisResult (tiny/small/medium/large/massive)
     * @return void
     */
    public function setBufferSizeForCategory(string $category): void
    {
        // Get category-specific buffer size recommendation
        $categoryBufferSize = self::CATEGORY_BUFFER_SIZES[$category] ?? self::MIN_BUFFER_SIZE;

        // Get the profile's configured buffer size as a ceiling
        $profileBufferSize = (int) $this->config->get('file_buffer_size', self::MIN_BUFFER_SIZE);

        // Use the smaller of category recommendation or profile limit
        // This ensures we don't exceed profile-based memory constraints
        $targetSize = min($categoryBufferSize, max($profileBufferSize, self::MIN_BUFFER_SIZE));

        // If aggressive profile and large files, allow larger buffer
        if ($this->config->getEffectiveProfile() === 'aggressive') {
            // In aggressive mode, for large/massive files, use larger buffers
            if (in_array($category, ['large', 'massive'], true)) {
                $targetSize = max($targetSize, $categoryBufferSize);
            }
        }

        // Apply with validation
        $this->bufferSize = $this->clampBufferSize($targetSize);
    }

    /**
     * Sets buffer size based on FileAnalysisResult
     *
     * Convenience method that extracts the category from the analysis result.
     *
     * @param FileAnalysisResult $analysisResult File analysis result
     * @return void
     */
    public function setBufferSizeFromAnalysis(FileAnalysisResult $analysisResult): void
    {
        $this->setBufferSizeForCategory($analysisResult->category);
    }

    /**
     * Gets the current buffer size
     *
     * @return int Buffer size in bytes
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * Sets buffer size directly (with validation)
     *
     * @param int $bufferSize Buffer size in bytes
     * @return void
     */
    public function setBufferSize(int $bufferSize): void
    {
        $this->bufferSize = $this->clampBufferSize($bufferSize);
    }

    /**
     * Gets buffer size constraints
     *
     * @return array{min: int, max: int}
     */
    public static function getBufferSizeConstraints(): array
    {
        return [
            'min' => self::MIN_BUFFER_SIZE,
            'max' => self::MAX_BUFFER_SIZE,
        ];
    }

    /**
     * Gets recommended buffer sizes by category
     *
     * @return array<string, int>
     */
    public static function getCategoryBufferSizes(): array
    {
        return self::CATEGORY_BUFFER_SIZES;
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

            // Skip .bz2 files if bz2 extension is not available
            if ($extension === 'bz2' && !function_exists('bzopen')) {
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
            'bz2' => 'BZ2',
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
                'message' => 'File type not allowed. Only .sql, .gz, .bz2 and .csv files are accepted.',
                'filename' => '',
            ];
        }

        // Check for bz2 extension availability
        if ($extension === 'bz2' && !function_exists('bzopen')) {
            return [
                'success' => false,
                'message' => 'BZip2 files require the PHP bz2 extension which is not installed.',
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

                // If extension is too long, truncate the extension instead
                if ($maxBaseLength < 1) {
                    $maxExtLength = 255 - 1 - 1; // 253 chars for extension, 1 for base, 1 for dot
                    $ext = substr($ext, 0, $maxExtLength);
                    $maxBaseLength = 1;
                }

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
        $this->bz2Mode = ($extension === 'bz2');

        if ($this->gzipMode) {
            if (!function_exists('gzopen')) {
                throw new RuntimeException('GZip support not available in PHP');
            }
            $this->fileHandle = @gzopen($filepath, 'rb');
        } elseif ($this->bz2Mode) {
            if (!function_exists('bzopen')) {
                throw new RuntimeException('BZip2 files require the PHP bz2 extension which is not installed');
            }
            $this->fileHandle = @bzopen($filepath, 'r');
        } else {
            $this->fileHandle = @fopen($filepath, 'rb');
        }

        if ($this->fileHandle === false) {
            $this->fileHandle = null;
            throw new RuntimeException("Cannot open file: {$cleanName}");
        }

        $this->currentFilename = $cleanName;
        $this->currentFilepath = $filepath;

        // Determine file size
        if (!$this->gzipMode && !$this->bz2Mode) {
            $this->fileSize = filesize($filepath) ?: 0;
        } else {
            // For gzip/bz2 files, we cannot know the uncompressed size
            // without reading the entire file, so leave it at 0
            $this->fileSize = 0;
        }

        return true;
    }

    /**
     * Sets file pointer position
     *
     * For BZ2 files, this uses a workaround that re-reads from the start
     * to the target position, as BZ2 streams don't support random seek.
     * This is O(n) with the offset position but preserves resume functionality.
     *
     * @param int $offset Position in bytes
     * @param callable|null $progressCallback Optional callback for seek progress (receives bytes read)
     * @return bool True if seek succeeds
     */
    public function seek(int $offset, ?callable $progressCallback = null): bool
    {
        if ($this->fileHandle === null) {
            return false;
        }

        // Clear read buffer when seeking
        $this->readBuffer = '';

        if ($this->gzipMode) {
            return @gzseek($this->fileHandle, $offset) === 0;
        }

        if ($this->bz2Mode) {
            return $this->seekBz2($offset, $progressCallback);
        }

        return @fseek($this->fileHandle, $offset) === 0;
    }

    /**
     * BZ2 seek workaround - re-reads from start to target position
     *
     * BZ2 streams don't support random seek like gzip's gzseek().
     * This method implements seek by:
     * 1. Closing the current stream
     * 2. Reopening the file from the beginning
     * 3. Reading and discarding bytes until reaching the target position
     *
     * Performance note: Time scales linearly O(n) with the offset position.
     *
     * @param int $offset Target position in bytes
     * @param callable|null $progressCallback Optional callback for seek progress
     * @return bool True if seek succeeds
     */
    private function seekBz2(int $offset, ?callable $progressCallback = null): bool
    {
        if ($offset === 0) {
            // Seeking to start: close and reopen
            @bzclose($this->fileHandle);
            $this->fileHandle = @bzopen($this->currentFilepath, 'r');
            return $this->fileHandle !== false;
        }

        // Close current stream
        @bzclose($this->fileHandle);

        // Reopen from start
        $this->fileHandle = @bzopen($this->currentFilepath, 'r');

        if ($this->fileHandle === false) {
            $this->fileHandle = null;
            return false;
        }

        // Read and discard bytes until we reach the target offset
        $bytesRead = 0;
        $remaining = $offset;

        while ($remaining > 0) {
            $chunkSize = min($remaining, $this->bufferSize);
            $chunk = @bzread($this->fileHandle, $chunkSize);

            if ($chunk === false || $chunk === '') {
                // Reached EOF before target position
                return false;
            }

            $actualRead = strlen($chunk);
            $bytesRead += $actualRead;
            $remaining -= $actualRead;

            // Call progress callback if provided
            if ($progressCallback !== null) {
                $progressCallback($bytesRead, $offset);
            }
        }

        return true;
    }

    /**
     * Retrieves current pointer position
     *
     * Accounts for buffered data that hasn't been "consumed" yet.
     *
     * @return int Position in bytes
     */
    public function tell(): int
    {
        if ($this->fileHandle === null) {
            return 0;
        }

        if ($this->gzipMode) {
            $pos = @gztell($this->fileHandle) ?: 0;
        } elseif ($this->bz2Mode) {
            // BZ2 doesn't have a tell function, so we track position manually
            // This is calculated based on how much we've read minus buffer
            // Note: This requires tracking position externally for accurate results
            // For now, we return 0 as BZ2 position tracking is handled by ImportService
            $pos = 0;
        } else {
            $pos = @ftell($this->fileHandle) ?: 0;
        }

        // Subtract buffered data that hasn't been consumed
        return $pos - strlen($this->readBuffer);
    }

    /**
     * Reads a line from file
     *
     * OPTIMIZED: Uses internal buffer to reduce system calls.
     * Reads larger chunks and extracts lines from buffer.
     * Buffer size is configurable via config for performance tuning.
     *
     * @return string|false Read line or false if end of file
     */
    public function readLine(): string|false
    {
        if ($this->fileHandle === null) {
            return false;
        }

        // Check if buffer contains a complete line
        $newlinePos = strpos($this->readBuffer, "\n");

        while ($newlinePos === false) {
            // Need to read more data - uses configurable buffer size
            if ($this->gzipMode) {
                $chunk = @gzread($this->fileHandle, $this->bufferSize);
                $isEof = @gzeof($this->fileHandle);
            } elseif ($this->bz2Mode) {
                $chunk = @bzread($this->fileHandle, $this->bufferSize);
                $isEof = ($chunk === false || $chunk === '');
            } else {
                $chunk = @fread($this->fileHandle, $this->bufferSize);
                $isEof = @feof($this->fileHandle);
            }

            if ($chunk === false || $chunk === '') {
                // End of file - return remaining buffer if not empty
                if ($this->readBuffer !== '') {
                    $line = $this->readBuffer;
                    $this->readBuffer = '';
                    return $line;
                }
                return false;
            }

            $this->readBuffer .= $chunk;
            $newlinePos = strpos($this->readBuffer, "\n");

            if ($isEof && $newlinePos === false) {
                // EOF reached, return remaining buffer
                if ($this->readBuffer !== '') {
                    $line = $this->readBuffer;
                    $this->readBuffer = '';
                    return $line;
                }
                return false;
            }
        }

        // Extract line from buffer (including newline)
        $line = substr($this->readBuffer, 0, $newlinePos + 1);
        $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);

        return $line;
    }

    /**
     * Checks if end of file reached
     *
     * Accounts for buffered data - not EOF if buffer still has data.
     *
     * @return bool True if end of file
     */
    public function eof(): bool
    {
        if ($this->fileHandle === null) {
            return true;
        }

        // Not EOF if buffer still has data
        if ($this->readBuffer !== '') {
            return false;
        }

        if ($this->gzipMode) {
            return @gzeof($this->fileHandle);
        }

        if ($this->bz2Mode) {
            // BZ2 doesn't have a dedicated EOF function
            // We check by attempting a small read
            $peek = @bzread($this->fileHandle, 1);
            if ($peek === false || $peek === '') {
                return true;
            }
            // Put the byte back into buffer
            $this->readBuffer = $peek;
            return false;
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
            } elseif ($this->bz2Mode) {
                @bzclose($this->fileHandle);
            } else {
                @fclose($this->fileHandle);
            }
            $this->fileHandle = null;
        }

        $this->gzipMode = false;
        $this->bz2Mode = false;
        $this->fileSize = 0;
        $this->currentFilename = '';
        $this->currentFilepath = '';
        $this->readBuffer = '';
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
     * @return int Size in bytes (0 for gzip/bz2 files)
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
     * Checks if file is in BZ2 mode
     *
     * @return bool True if BZ2 mode
     */
    public function isBz2Mode(): bool
    {
        return $this->bz2Mode;
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
