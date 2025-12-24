# BigDump - Claude Code Guidelines

## Project Overview

BigDump is a PHP-based MySQL dump importer designed for web servers with strict execution time limits. It imports large SQL files in staggered sessions, avoiding timeout issues.

**Architecture**: MVC with dependency injection
**PHP Version**: 8.1+
**Key Dependencies**: MySQLi extension

## Project Structure

```
src/
├── Config/           # Configuration management
├── Controllers/      # HTTP request handlers
├── Core/             # Framework components (Router, View, Request, Response)
├── Models/           # Data and file handling (Database, FileHandler, SqlParser)
└── Services/         # Business logic (ImportService, AutoTunerService, etc.)
```

## Key Components

### Performance-Critical Files

1. **`src/Models/SqlParser.php`** - SQL parsing with quote detection
   - `analyzeQuotes()`: Uses `strpos()` for O(1) quote position jumps
   - Handles multi-line strings, escaped quotes, SQL delimiters

2. **`src/Models/FileHandler.php`** - Buffered file reading
   - 64KB internal buffer for reduced system calls
   - `tell()` accounts for buffered but unconsumed data
   - Supports both normal and gzip files

3. **`src/Services/InsertBatcherService.php`** - INSERT query batching
   - Groups consecutive simple INSERTs into multi-value queries
   - `parseSimpleInsert()`: Uses string functions instead of regex

4. **`src/Config/Config.php`** - Default configuration
   - Pre-queries: `autocommit=0`, `unique_checks=0`, `foreign_key_checks=0`
   - Post-queries: Restore settings after import

### Import Flow

1. `BigDumpController` receives request
2. `ImportService::executeSession()` orchestrates import
3. `FileHandler::readLine()` reads buffered lines
4. `SqlParser::parseLine()` extracts complete queries
5. `InsertBatcherService::process()` batches INSERTs
6. `Database::query()` executes SQL

## Coding Standards

- PSR-4 autoloading
- Strict types enabled (`declare(strict_types=1)`)
- DocBlocks with `@param`, `@return`, `@throws`
- Type hints for all parameters and return values

## Performance Guidelines

When modifying performance-critical code:

1. **Avoid character-by-character loops** - Use `strpos()`, `substr()`, `str_contains()`
2. **Minimize regex usage** - String functions are faster for simple patterns
3. **Buffer I/O operations** - Batch reads/writes when possible
4. **Maintain buffer state** - Update `seek()`, `tell()`, `eof()` when modifying buffers

## Testing Changes

No automated tests exist. Manual testing:

```bash
# Syntax check
php -l src/Models/SqlParser.php

# Test import with small file
# 1. Place test.sql in uploads/
# 2. Configure config/config.php
# 3. Run import via browser
```

## Common Tasks

### Adding a new pre-query
Edit `src/Config/Config.php`:
```php
'pre_queries' => [
    'SET autocommit=0',
    'SET your_new_setting=value',  // Add here
],
```

### Modifying SQL parsing
Edit `src/Models/SqlParser.php`:
- Quote handling: `analyzeQuotes()`
- Query completion: `isQueryComplete()`
- Delimiter changes: `isDelimiterCommand()`

### Adjusting buffer sizes
- File buffer: `src/Models/FileHandler.php` → `$bufferSize`
- INSERT batch: `src/Config/Config.php` → `insert_batch_size`
- Query memory limit: `src/Config/Config.php` → `max_query_memory`
