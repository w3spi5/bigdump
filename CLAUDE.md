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
   - Configurable buffer size: 64KB-256KB based on profile (v2.19)
   - `tell()` accounts for buffered but unconsumed data
   - Supports both normal and gzip files
   - `setBufferSizeForCategory()`: Adjusts buffer based on file size category

3. **`src/Services/InsertBatcherService.php`** - INSERT query batching
   - Groups consecutive simple INSERTs into multi-value queries
   - `parseSimpleInsert()`: Uses string functions instead of regex
   - Supports INSERT IGNORE batching (v2.19)
   - Configurable batch sizes: 2000/5000 based on profile (v2.19)
   - Adaptive batch sizing based on average row size (v2.19)

4. **`src/Config/Config.php`** - Configuration with performance profiles
   - Pre-queries: `autocommit=0`, `unique_checks=0`, `foreign_key_checks=0`
   - Post-queries: Restore settings after import
   - **Performance Profile System** (v2.19): `conservative` / `aggressive` modes
   - `getEffectiveProfile()`: Returns validated profile after memory check

5. **`src/Services/AutoTunerService.php`** - Dynamic batch sizing (v2.19)
   - Profile-aware: multiplier 1.3x, safety margin 70% in aggressive mode
   - Memory caching (1s TTL) reduces `memory_get_usage()` overhead
   - System resources cache (60s TTL)

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

### Automated Tests (v2.19)

```bash
# Run all feature tests (36 tests)
php tests/PerformanceProfileTest.php    # 10 tests - Profile system
php tests/InsertBatcherTest.php         # 7 tests - INSERT batching
php tests/FileHandlerBufferTest.php     # 7 tests - File I/O buffers
php tests/AutoTunerProfileTest.php      # 7 tests - AutoTuner profiles
php tests/IntegrationTest.php           # 5 tests - Integration tests
```

### Manual Testing

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

### Adjusting buffer sizes (v2.19)
- **Performance Profile**: `src/Config/Config.php` → `performance_profile` (`conservative` / `aggressive`)
- File buffer: `src/Config/Config.php` → `file_buffer_size` (64KB-256KB)
- INSERT batch: `src/Config/Config.php` → `insert_batch_size` (2000/5000)
- Max batch bytes: `src/Config/Config.php` → `max_batch_bytes` (16MB/32MB)
- COMMIT frequency: `src/Config/Config.php` → `commit_frequency` (1/3)
