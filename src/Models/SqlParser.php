<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;

/**
 * SqlParser Class - SQL Query Parser
 *
 * This class parses SQL lines to extract complete queries,
 * properly handling:
 * - Multi-line strings
 * - Custom delimiters (stored procedures)
 * - SQL comments
 * - Escape characters
 *
 * Improvements over original:
 * - Proper handling of \\\\ (double backslash) before quotes
 * - DELIMITER detection only outside strings
 * - Protection against infinite memory accumulation
 * - Optimized: skips analyzeQuotes for comments/empty lines when not in string
 *
 * @package BigDump\Models
 * @author  w3spi5
 */
class SqlParser
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Current query delimiter
     * @var string
     */
    private string $delimiter;

    /**
     * Valid string quote characters (both single and double quotes in SQL)
     * @var array<int, string>
     */
    private array $stringQuotes = ["'", '"'];

    /**
     * Current active quote character (when inside a string)
     * @var string
     */
    private string $activeQuote = '';

    /**
     * Comment markers
     * @var array<int, string>
     */
    private array $commentMarkers;

    /**
     * Indicates if we are inside a string
     * @var bool
     */
    private bool $inString = false;

    /**
     * Query being built
     * @var string
     */
    private string $currentQuery = '';

    /**
     * Number of lines in current query
     * @var int
     */
    private int $queryLineCount = 0;

    /**
     * Maximum number of lines per query
     * @var int
     */
    private int $maxQueryLines;

    /**
     * Maximum memory size for a query
     * @var int
     */
    private int $maxQueryMemory;

    /**
     * Constructor
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->delimiter = $config->get('delimiter', ';');
        // Support both single and double quotes for SQL strings
        $this->stringQuotes = ["'", '"'];
        // Note: /*! (MySQL conditional comments) are NOT in the default list
        // because they contain valid SQL code that MySQL executes
        $this->commentMarkers = $config->get('comment_markers', ['#', '-- ']);
        $this->maxQueryLines = $config->get('max_query_lines', 10000);
        $this->maxQueryMemory = $config->get('max_query_memory', 10485760);
    }

    /**
     * Resets parser state
     *
     * @return void
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
     * @return void
     */
    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Retrieves current delimiter
     *
     * @return string Delimiter
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Parses a line and returns complete query if available
     *
     * OPTIMIZED (v2.25): When NOT inside a string, checks for comments/empty
     * lines BEFORE calling analyzeQuotes, saving CPU cycles on comment-heavy dumps.
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

        // OPTIMIZATION: When NOT in a string, perform early-exit checks
        // before the more expensive analyzeQuotes call
        if (!$this->inString) {
            // Detect DELIMITER commands (only if not in a string)
            if ($this->isDelimiterCommand($line)) {
                $newDelimiter = $this->extractDelimiter($line);

                if ($newDelimiter !== null) {
                    $this->delimiter = $newDelimiter;
                    $result['delimiter_changed'] = true;
                }

                return $result;
            }

            // OPTIMIZATION (v2.25): Skip analyzeQuotes for comments and empty lines
            // when NOT inside a string. This saves significant CPU on comment-heavy dumps.
            if ($this->isCommentOrEmpty($line)) {
                return $result;
            }
        }

        // Check memory limit
        if (strlen($this->currentQuery) + strlen($line) > $this->maxQueryMemory) {
            $result['error'] = "Query exceeds maximum memory limit ({$this->maxQueryMemory} bytes)";
            $this->reset();
            return $result;
        }

        // Analyze quotes to know if we enter/exit a string
        $this->analyzeQuotes($line);

        // Add line to current query
        $this->currentQuery .= $line;

        // Count lines only if not in a string
        if (!$this->inString) {
            $this->queryLineCount++;
        }

        // Check line limit
        if ($this->queryLineCount > $this->maxQueryLines) {
            $result['error'] = "Query exceeds maximum line count ({$this->maxQueryLines} lines). " .
                "This may indicate extended inserts or a very long procedure. " .
                "Increase max_query_lines in config if this is expected.";
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

        // Format: DELIMITER xxx
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
     * OPTIMIZED: Uses strpos() to jump directly to quote positions
     * instead of iterating character by character.
     *
     * This method properly handles:
     * - Both single quotes (') and double quotes (")
     * - Double backslashes (\\) before quotes
     * - Doubled quotes as SQL escape mechanism (e.g., 'It''s OK')
     * - Matching quote types (a string opened with ' must close with ')
     *
     * @param string $line Line to analyze
     * @return void
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
                    // No closing quote found in this line
                    return;
                }

                // Check if it's a doubled quote (SQL escape: '' or "")
                if ($quotePos + 1 < $length && $line[$quotePos + 1] === $this->activeQuote) {
                    // Skip both characters, stay in string
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

                // If even number of backslashes (or zero), quote closes the string
                if ($backslashes % 2 === 0) {
                    $this->inString = false;
                    $this->activeQuote = '';
                }

                $pos = $quotePos + 1;
            } else {
                // Find the next single or double quote
                $singlePos = strpos($line, "'", $pos);
                $doublePos = strpos($line, '"', $pos);

                // Determine which quote comes first
                if ($singlePos === false && $doublePos === false) {
                    // No quotes found
                    return;
                }

                if ($singlePos === false) {
                    $nextQuotePos = $doublePos;
                    $quoteChar = '"';
                } elseif ($doublePos === false) {
                    $nextQuotePos = $singlePos;
                    $quoteChar = "'";
                } else {
                    // Both found, take the first one
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

        // Remove final delimiter
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
     * Used at end of file to retrieve a possible
     * query not terminated by a delimiter.
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
     * Sets in-string state (for session restoration)
     *
     * @param bool $inString In-string state
     * @param string $activeQuote The quote character that opened the string ('' or "")
     * @return void
     */
    public function setInString(bool $inString, string $activeQuote = "'"): void
    {
        $this->inString = $inString;
        $this->activeQuote = $inString ? $activeQuote : '';
    }

    /**
     * Gets active quote character
     *
     * @return string Active quote character ('' or ""), empty if not in string
     */
    public function getActiveQuote(): string
    {
        return $this->activeQuote;
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
     * Sets current query buffer (for session restoration)
     *
     * @param string $query Query to restore
     * @return void
     */
    public function setCurrentQuery(string $query): void
    {
        $this->currentQuery = $query;
        // Count lines in restored query
        $this->queryLineCount = substr_count($query, "\n");
    }

    /**
     * Retrieves line count of current query
     *
     * @return int Number of lines
     */
    public function getQueryLineCount(): int
    {
        return $this->queryLineCount;
    }

    /**
     * Converts a CSV line to INSERT query
     *
     * @param string $line CSV line
     * @param string $table Destination table
     * @return string INSERT query
     */
    public function csvToInsert(string $line, string $table): string
    {
        // Validate table name to prevent SQL injection
        // MySQL table names can contain letters, digits, underscores, and dollar signs
        $safeTable = preg_replace('/[^a-zA-Z0-9_$]/', '', $table);
        if ($safeTable !== $table || empty($safeTable)) {
            throw new \RuntimeException("Invalid table name: {$table}");
        }

        $csvConfig = $this->config->getCsv();
        $delimiter = $csvConfig['delimiter'];
        $enclosure = $csvConfig['enclosure'];
        $addQuotes = $csvConfig['add_quotes'];
        $addSlashes = $csvConfig['add_slashes'];

        // Parse CSV line correctly (handles fields with delimiters)
        $fields = $this->parseCsvLine($line, $delimiter, $enclosure);

        // Prepare values
        $values = [];

        foreach ($fields as $field) {
            if ($addSlashes) {
                $field = addslashes($field);
            }

            if ($addQuotes) {
                $values[] = "'" . $field . "'";
            } else {
                $values[] = $field;
            }
        }

        return "INSERT INTO `{$safeTable}` VALUES (" . implode(',', $values) . ")";
    }

    /**
     * Parses a CSV line correctly
     *
     * Handles cases where delimiter appears in an enclosed field.
     *
     * @param string $line CSV line
     * @param string $delimiter Delimiter
     * @param string $enclosure Enclosure character
     * @return array<int, string> Parsed fields
     */
    private function parseCsvLine(string $line, string $delimiter, string $enclosure): array
    {
        // Remove line endings
        $line = rtrim($line, "\r\n");

        // Use str_getcsv for correct parsing
        // Note: str_getcsv always returns an array (never false)
        return str_getcsv($line, $delimiter, $enclosure);
    }

    /**
     * Checks if a line is a valid CSV line
     *
     * @param string $line Line to check
     * @return bool True if valid CSV
     */
    public function isValidCsvLine(string $line): bool
    {
        $trimmed = trim($line);

        // Ignore empty lines
        if ($trimmed === '') {
            return false;
        }

        // Ignore CSV comment lines
        if (str_starts_with($trimmed, '#')) {
            return false;
        }

        return true;
    }
}
