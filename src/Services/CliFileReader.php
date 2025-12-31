<?php

declare(strict_types=1);

namespace BigDump\Services;

use RuntimeException;

/**
 * CLI File Reader - Standalone File Reading for CLI Tool
 *
 * Reads SQL dump files (.sql, .sql.gz, .sql.bz2) without requiring
 * Config class or upload directory constraints.
 *
 * Features:
 * - Buffered reading (128KB default)
 * - BOM removal on first read
 * - Auto-detection of compression from file extension
 * - File size tracking for progress calculation
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class CliFileReader
{
    /**
     * File path (absolute)
     */
    private string $filePath;

    /**
     * File handle
     * @var resource|null
     */
    private $fileHandle = null;

    /**
     * Gzip mode flag
     */
    private bool $gzipMode = false;

    /**
     * BZ2 mode flag
     */
    private bool $bz2Mode = false;

    /**
     * File size in bytes
     */
    private int $fileSize = 0;

    /**
     * Internal read buffer
     */
    private string $readBuffer = '';

    /**
     * Buffer size for chunked reading (128KB)
     */
    private int $bufferSize = 131072;

    /**
     * Total bytes read (for progress tracking)
     */
    private int $bytesRead = 0;

    /**
     * Whether first line has been read (for BOM removal)
     */
    private bool $firstLineRead = false;

    /**
     * Constructor
     *
     * @param string $filePath Absolute path to SQL file
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Opens the file for reading
     *
     * @return bool True if file opened successfully
     * @throws RuntimeException If file cannot be opened
     */
    public function open(): bool
    {
        $this->close();

        if (!file_exists($this->filePath)) {
            throw new RuntimeException("File not found: {$this->filePath}");
        }

        if (!is_readable($this->filePath)) {
            throw new RuntimeException("File not readable: {$this->filePath}");
        }

        // Detect compression mode from extension
        $basename = strtolower(basename($this->filePath));
        $this->gzipMode = str_ends_with($basename, '.gz');
        $this->bz2Mode = str_ends_with($basename, '.bz2');

        // Open file based on compression type
        if ($this->gzipMode) {
            if (!function_exists('gzopen')) {
                throw new RuntimeException('GZip support not available in PHP');
            }
            $this->fileHandle = @gzopen($this->filePath, 'rb');
        } elseif ($this->bz2Mode) {
            if (!function_exists('bzopen')) {
                throw new RuntimeException('BZip2 support requires the PHP bz2 extension');
            }
            $this->fileHandle = @bzopen($this->filePath, 'r');
        } else {
            $this->fileHandle = @fopen($this->filePath, 'rb');
        }

        if ($this->fileHandle === false || $this->fileHandle === null) {
            $this->fileHandle = null;
            throw new RuntimeException("Cannot open file: {$this->filePath}");
        }

        // Store file size (compressed size for gz/bz2)
        $this->fileSize = filesize($this->filePath) ?: 0;

        return true;
    }

    /**
     * Reads a line from the file
     *
     * Uses buffered reading for performance.
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
            // Read more data into buffer
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
                    $this->bytesRead += strlen($line);
                    $this->readBuffer = '';
                    return $this->processFirstLine($line);
                }
                return false;
            }

            $this->readBuffer .= $chunk;
            $newlinePos = strpos($this->readBuffer, "\n");

            if ($isEof && $newlinePos === false) {
                // EOF reached, return remaining buffer
                if ($this->readBuffer !== '') {
                    $line = $this->readBuffer;
                    $this->bytesRead += strlen($line);
                    $this->readBuffer = '';
                    return $this->processFirstLine($line);
                }
                return false;
            }
        }

        // Extract line from buffer (including newline)
        $line = substr($this->readBuffer, 0, $newlinePos + 1);
        $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);
        $this->bytesRead += strlen($line);

        return $this->processFirstLine($line);
    }

    /**
     * Process first line for BOM removal
     *
     * @param string $line Line to process
     * @return string Processed line
     */
    private function processFirstLine(string $line): string
    {
        if (!$this->firstLineRead) {
            $this->firstLineRead = true;
            return $this->removeBom($line);
        }
        return $line;
    }

    /**
     * Removes BOM (Byte Order Mark) from a string
     *
     * Handles UTF-8, UTF-16 LE/BE, UTF-32 LE/BE
     *
     * @param string $string String to clean
     * @return string String without BOM
     */
    private function removeBom(string $string): string
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
     * Checks if end of file reached
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
        $this->readBuffer = '';
        $this->firstLineRead = false;
    }

    /**
     * Gets the file size in bytes
     *
     * @return int File size (compressed size for gz/bz2)
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Gets total bytes read
     *
     * @return int Bytes read (uncompressed)
     */
    public function getBytesRead(): int
    {
        return $this->bytesRead;
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
     * Checks if file is compressed
     *
     * @return bool True if compressed
     */
    public function isCompressed(): bool
    {
        return $this->gzipMode || $this->bz2Mode;
    }

    /**
     * Gets the file path
     *
     * @return string File path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Destructor - ensures file is closed
     */
    public function __destruct()
    {
        $this->close();
    }
}
