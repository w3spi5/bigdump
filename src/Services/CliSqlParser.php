<?php

declare(strict_types=1);

namespace BigDump\Services;

/**
 * CLI SQL Parser - Standalone SQL Parser for CLI Tool
 *
 * Parses SQL lines to extract complete queries without requiring Config class.
 * Handles multi-line strings, custom delimiters, and SQL comments.
 *
 * Features:
 * - Multi-line string handling (single and double quotes)
 * - DELIMITER command detection
 * - Comment filtering (# and -- patterns)
 * - Memory protection with configurable limits
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class CliSqlParser
{
    /**
     * Current query delimiter
     */
    private string $delimiter = ';';

    /**
     * Whether inside a string
     */
    private bool $inString = false;

    /**
     * Active quote character when in string
     */
    private string $activeQuote = '';

    /**
     * Query being built
     */
    private string $currentQuery = '';

    /**
     * Number of lines in current query
     */
    private int $queryLineCount = 0;

    /**
     * Comment markers
     * @var array<int, string>
     */
    private array $commentMarkers = ['#', '-- '];

    /**
     * Maximum number of lines per query
     */
    private int $maxQueryLines = 10000;

    /**
     * Maximum memory size for a query (10MB)
     */
    private int $maxQueryMemory = 10485760;

    /**
     * Constructor with optional configuration
     *
     * @param array<string, mixed> $options Optional configuration
     */
    public function __construct(array $options = [])
    {
        $this->delimiter = $options['delimiter'] ?? ';';
        $this->maxQueryLines = $options['max_query_lines'] ?? 10000;
        $this->maxQueryMemory = $options['max_query_memory'] ?? 10485760;
    }

    /**
     * Resets parser state
     */
    public function reset(): void
    {
        $this->inString = false;
        $this->activeQuote = '';
        $this->currentQuery = '';
        $this->queryLineCount = 0;
    }

    /**
     * Sets query delimiter
     *
     * @param string $delimiter New delimiter
     */
    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Gets current delimiter
     *
     * @return string Current delimiter
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Parses a line and returns complete query if available
     *
     * @param string $line Line to parse
     * @return array{query: string|null, error: string|null, delimiter_changed: bool} Parsing result
     */
    public function parseLine(string $line): array
    {
        $result = [
            'query' => null,
            'error' => null,
            'delimiter_changed' => false,
        ];

        // Normalize line endings
        $line = str_replace(["\r\n", "\r"], "\n", $line);

        // Detect DELIMITER commands (only if not in a string)
        if (!$this->inString && $this->isDelimiterCommand($line)) {
            $newDelimiter = $this->extractDelimiter($line);

            if ($newDelimiter !== null) {
                $this->delimiter = $newDelimiter;
                $result['delimiter_changed'] = true;
            }

            return $result;
        }

        // Ignore comments and empty lines (only if not in a string)
        if (!$this->inString && $this->isCommentOrEmpty($line)) {
            return $result;
        }

        // Check memory limit
        if (strlen($this->currentQuery) + strlen($line) > $this->maxQueryMemory) {
            $result['error'] = "Query exceeds maximum memory limit ({$this->maxQueryMemory} bytes)";
            $this->reset();
            return $result;
        }

        // Analyze quotes to determine in-string state
        $this->analyzeQuotes($line);

        // Add line to current query
        $this->currentQuery .= $line;

        // Count lines only if not in a string
        if (!$this->inString) {
            $this->queryLineCount++;
        }

        // Check line limit
        if ($this->queryLineCount > $this->maxQueryLines) {
            $result['error'] = "Query exceeds maximum line count ({$this->maxQueryLines} lines)";
            $this->reset();
            return $result;
        }

        // Check if query is complete (delimiter at end, outside string)
        if (!$this->inString && $this->isQueryComplete()) {
            $query = $this->extractQuery();
            $this->reset();

            if (!empty(trim($query))) {
                $result['query'] = $query;
            }
        }

        return $result;
    }

    /**
     * Checks if a line is a DELIMITER command
     *
     * @param string $line Line to check
     * @return bool True if DELIMITER command
     */
    private function isDelimiterCommand(string $line): bool
    {
        $trimmed = ltrim($line);
        return stripos($trimmed, 'DELIMITER ') === 0 || strcasecmp(trim($trimmed), 'DELIMITER') === 0;
    }

    /**
     * Extracts new delimiter from a DELIMITER command
     *
     * @param string $line Line containing the command
     * @return string|null New delimiter or null
     */
    private function extractDelimiter(string $line): ?string
    {
        $trimmed = trim($line);

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = trim($matches[1]);

            if (!empty($delimiter)) {
                return $delimiter;
            }
        }

        return null;
    }

    /**
     * Checks if a line is a comment or empty
     *
     * @param string $line Line to check
     * @return bool True if comment or empty
     */
    private function isCommentOrEmpty(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return true;
        }

        foreach ($this->commentMarkers as $marker) {
            if (str_starts_with($trimmed, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyzes quotes in a line to determine in-string state
     *
     * Handles:
     * - Both single quotes (') and double quotes (")
     * - Double backslashes before quotes
     * - Doubled quotes as SQL escape mechanism
     *
     * @param string $line Line to analyze
     */
    private function analyzeQuotes(string $line): void
    {
        $length = strlen($line);
        $pos = 0;

        while ($pos < $length) {
            if ($this->inString) {
                // Find the next occurrence of the active quote
                $quotePos = strpos($line, $this->activeQuote, $pos);

                if ($quotePos === false) {
                    return;
                }

                // Check if it's a doubled quote (SQL escape)
                if ($quotePos + 1 < $length && $line[$quotePos + 1] === $this->activeQuote) {
                    $pos = $quotePos + 2;
                    continue;
                }

                // Count preceding backslashes
                $backslashes = 0;
                $j = $quotePos - 1;
                while ($j >= 0 && $line[$j] === '\\') {
                    $backslashes++;
                    $j--;
                }

                // If even number of backslashes, quote closes the string
                if ($backslashes % 2 === 0) {
                    $this->inString = false;
                    $this->activeQuote = '';
                }

                $pos = $quotePos + 1;
            } else {
                // Find the next single or double quote
                $singlePos = strpos($line, "'", $pos);
                $doublePos = strpos($line, '"', $pos);

                if ($singlePos === false && $doublePos === false) {
                    return;
                }

                if ($singlePos === false) {
                    $nextQuotePos = $doublePos;
                    $quoteChar = '"';
                } elseif ($doublePos === false) {
                    $nextQuotePos = $singlePos;
                    $quoteChar = "'";
                } else {
                    if ($singlePos < $doublePos) {
                        $nextQuotePos = $singlePos;
                        $quoteChar = "'";
                    } else {
                        $nextQuotePos = $doublePos;
                        $quoteChar = '"';
                    }
                }

                $this->inString = true;
                $this->activeQuote = $quoteChar;
                $pos = $nextQuotePos + 1;
            }
        }
    }

    /**
     * Checks if current query is complete
     *
     * @return bool True if query is complete
     */
    private function isQueryComplete(): bool
    {
        if ($this->delimiter === '') {
            return true;
        }

        $trimmed = rtrim($this->currentQuery);

        return str_ends_with($trimmed, $this->delimiter);
    }

    /**
     * Extracts complete query (without final delimiter)
     *
     * @return string Extracted query
     */
    private function extractQuery(): string
    {
        $query = $this->currentQuery;

        if ($this->delimiter !== '') {
            $query = rtrim($query);
            $delimiterLength = strlen($this->delimiter);

            if (str_ends_with($query, $this->delimiter)) {
                $query = substr($query, 0, -$delimiterLength);
            }
        }

        return trim($query);
    }

    /**
     * Returns pending incomplete query
     *
     * Used at end of file to retrieve a possible query not terminated by delimiter.
     *
     * @return string|null Query or null if empty
     */
    public function getPendingQuery(): ?string
    {
        $query = trim($this->currentQuery);

        if (empty($query)) {
            return null;
        }

        // Remove possible final delimiter
        if ($this->delimiter !== '' && str_ends_with($query, $this->delimiter)) {
            $query = substr($query, 0, -strlen($this->delimiter));
            $query = trim($query);
        }

        return empty($query) ? null : $query;
    }

    /**
     * Checks if parser is inside a string
     *
     * @return bool True if in string
     */
    public function isInString(): bool
    {
        return $this->inString;
    }

    /**
     * Gets current query buffer
     *
     * @return string Current query buffer
     */
    public function getCurrentQuery(): string
    {
        return $this->currentQuery;
    }

    /**
     * Gets line count of current query
     *
     * @return int Number of lines
     */
    public function getQueryLineCount(): int
    {
        return $this->queryLineCount;
    }
}
