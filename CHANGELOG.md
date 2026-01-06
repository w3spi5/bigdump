# Changelog

All notable changes to BigDump are documented in this file.

> **Note**: BigDump was originally created by Alexey Ozerov in 2003. Version 2.x is a complete MVC refactoring by w3spi5 (2025).

## [2.25] - 2025-01-06 - Performance Audit Import Optimization

### Added in 2.25

- **Auto-Aggressive Mode for Large Files**: Automatic performance profile upgrade
  - Files >100MB automatically use aggressive profile (configurable via `auto_profile_threshold`)
  - New `setTemporary()` method in Config for runtime profile overrides
  - Triggered in ImportService constructor based on file size detection
  - Memory validation: requires 128MB+ PHP memory_limit

- **Persistent Database Connections**: Reduced reconnection overhead
  - New `persistent_connections` config option (default: false)
  - Uses MySQLi `p:` hostname prefix for persistent connections
  - Connection validation before reuse via `validateConnection()` method
  - ~16,000 fewer reconnects for 2GB file imports
  - Documented risks for shared hosting environments

- **Extended INSERT Detection**: Optimized mysqldump extended-insert handling
  - Detects multi-VALUE INSERT patterns from `mysqldump --extended-insert`
  - INSERTs with ≥2 `),(` patterns executed directly without re-batching
  - New `extendedInsertCount` statistic in InsertBatcherService
  - Prevents wasted work on already-optimized dumps

- **Compression-Aware Session Sizing**: Memory-optimized batch sizes by file type
  - New compression multipliers: Plain SQL (1.5×), GZIP (1.0×), BZ2 (0.7×)
  - FileHandler: `getCompressionType()` and `getCurrentCompressionType()` methods
  - AutoTunerService: `setCompressionType()`, `getCompressionMultiplier()` methods
  - Prevents memory exhaustion on high-overhead BZ2 decompression

- **Performance Tests**: Comprehensive test coverage for all optimizations
  - `tests/AutoProfileTest.php`: Auto-aggressive mode tests (7 tests)
  - `tests/SqlParserOptimizationTest.php`: Quote analysis skip tests (9 tests)
  - `tests/PersistentConnectionTest.php`: Persistent connection tests (6 tests)
  - `tests/ExtendedInsertTest.php`: Extended INSERT detection tests (11 tests)
  - `tests/CompressionAwareTest.php`: Compression-aware sizing tests (14 tests)

### Changed in 2.25

- **Increased Default Batch Sizes**: Better performance out of the box
  - `min_batch_size`: 3000 → 5000
  - Conservative `linespersession`: 3000 → 5000
  - Aggressive `linespersession`: 5000 → 10000
  - AutoTuner respects new minimums

- **Optimized Quote Analysis**: Skip analyzeQuotes() for non-SQL lines
  - SqlParser now checks `inString` state before comment/empty line detection
  - Early return for comments and empty lines when not inside a string literal
  - Reduced CPU overhead on comment-heavy dumps

### Performance Targets

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| 100MB import time | ~60s | ~40s | 33% faster |
| 500MB import time | ~5min | ~3min | 40% faster |
| Session count (1GB) | ~33K | ~10K | 70% reduction |
| Memory peak | Variable | <64MB | Controlled |

---

## [2.24] - 2025-01-05 - Smart Table Reset & Bug Fixes

### Added in 2.24

- **Smart Table Reset**: Automatic DROP of tables before import
  - Pre-scans SQL file for CREATE TABLE statements (supports .sql, .gz, .bz2)
  - Automatically drops existing tables before import to prevent "Table already exists" errors
  - New methods in `FileAnalysisService`: `findCreateTables()`, `dropTablesForFile()`
  - Security: Table name validation with regex, backtick quoting
  - Graceful failure: import continues even if Smart Reset fails

- **Smart Table Reset Tests**: New test file `tests/SmartTableResetTest.php`
  - 7 tests covering table extraction, gzip support, deduplication, security validation

### Fixed in 2.24

- **Session cleanup after SSE error**: Session now properly cleared after import errors
  - Previously, failed imports left stale session data causing silent failures on retry
  - Added `clearSessionDirect()` call after error event in `sseImport()`

- **Navigation links**: All "Back to Home" links now use `$scriptUri`
  - Fixed `href="../"` in `error.php` (line 88)
  - Fixed `href="/"` in `import.php` (line 345) - was going to server root!
  - Fixed `href="./"` in `layout.php` and `layout_phar.php` (header logo)
  - All navigation now works correctly regardless of installation path

### Changed in 2.24

- **Header styling**: Theme-aware pastel gradients for light and dark modes
  - Light mode: soft yellow/orange gradient (`#fcd34d → #fdba74 → #fbbf24`)
  - Dark mode: muted amber/orange gradient (`#d97706 → #ea580c → #c2410c`)
  - CSS class-based theming (`.header-gradient`, `.header-title`, `.header-subtitle`)

---

## [2.23] - 2025-12-31 - Single-File PHAR Distribution

### Added in 2.23

- **Single-File PHAR Distribution**: Package BigDump as a standalone `.phar` file like Adminer
  - Upload one file, access via browser — zero installation
  - Automatic web/CLI mode detection via `php_sapi_name()`
  - All assets (CSS/JS/SVG) inlined directly into HTML output
  - External configuration via `bigdump-config.php` next to PHAR
  - GitHub Actions workflow for automated release builds

- **PHAR Core Components**: New utilities for PHAR-aware operation
  - `src/Core/PharContext.php`: PHAR detection and path resolution utilities
  - `build/build-phar.php`: Build script with compression and size reporting
  - `build/stubs/web-entry.php`: Web mode bootstrap for PHAR
  - `build/stubs/cli-entry.php`: CLI mode bootstrap for PHAR
  - `templates/layout_phar.php`: PHAR-specific layout with inlined assets

- **Asset Inlining System**: Adminer-style asset embedding
  - CSS inlined in `<style>` tags (no external requests)
  - JavaScript inlined in `<script>` tags
  - SVG icons embedded directly in HTML
  - View class extended with `setPharMode()`, `getInlinedCss()`, `getInlinedJs()`, `getInlinedIcons()`

- **PHAR Test Suite**: Comprehensive test coverage (58 tests)
  - `tests/PharContextTest.php`: Path resolution and detection tests
  - `tests/PharBuildTest.php`: Build script functionality tests
  - `tests/PharEntryPointsTest.php`: Web/CLI entry point tests
  - `tests/ViewAssetInliningTest.php`: Asset inlining tests

### Changed in 2.23

- **Application.php**: Added PHAR mode support and options
  - New `$isPharMode` property and `isPharMode()` method
  - Constructor accepts PHAR options array
  - Passes PHAR mode to View instance

- **View.php**: Extended for PHAR asset inlining
  - Added asset caching properties
  - Auto-switches to `layout_phar` template in PHAR mode
  - New methods for inline asset retrieval

### PHAR Usage

```bash
# Build PHAR locally
php -d phar.readonly=0 build/build-phar.php

# Output:
# BigDump PHAR Builder
# ====================
# PHP files:  22 (compressed)
# Templates:  6 (compressed)
# Assets:     8 (uncompressed)
# ✓ PHAR created: dist/bigdump.phar (478 KB)

# Web mode: upload bigdump.phar to server, access via browser
# Copy bigdump-config.example.php → bigdump-config.php, configure

# CLI mode
php bigdump.phar --version
php bigdump.phar dump.sql -o optimized.sql
php bigdump.phar dump.sql.gz -o optimized.sql --profile=aggressive
```

### PHAR Contents

| Component | Compression | Purpose |
|-----------|-------------|---------|
| `src/**/*.php` | GZ | PHP classes |
| `templates/*.php` | GZ | View templates |
| `assets/dist/*` | None | CSS/JS for inlining |
| `assets/icons.svg` | None | SVG icons for inlining |
| `cli-entry.php` | GZ | CLI bootstrap |
| `web-entry.php` | GZ | Web bootstrap |

### Files Added in 2.23

| File | Purpose |
|------|---------|
| `src/Core/PharContext.php` | PHAR detection and path utilities |
| `build/build-phar.php` | PHAR build script |
| `build/stubs/web-entry.php` | Web mode entry point |
| `build/stubs/cli-entry.php` | CLI mode entry point |
| `templates/layout_phar.php` | PHAR layout with inlined assets |
| `.github/workflows/build-phar.yml` | CI/CD workflow for releases |
| `tests/PharContextTest.php` | PharContext unit tests |
| `tests/PharBuildTest.php` | Build script tests |
| `tests/PharEntryPointsTest.php` | Entry point tests |
| `tests/ViewAssetInliningTest.php` | Asset inlining tests |

### Files Modified in 2.23

| File | Change |
|------|--------|
| `src/Core/Application.php` | Added PHAR mode support |
| `src/Core/View.php` | Added asset inlining methods |
| `.gitignore` | Added `dist/` directory |

### Generated Files (at build/release)

| File | Description |
|------|-------------|
| `dist/bigdump.phar` | Compiled PHAR archive (~478 KB) |
| `dist/bigdump-config.example.php` | Example configuration for users |

---

## [2.22] - 2025-12-31 - CLI SQL Optimizer & JSON Migration

### Added in 2.22

- **CLI SQL Optimizer**: Standalone command-line tool for SQL dump optimization without database connection
  - New `cli.php` entry point for command-line usage
  - Rewrites SQL dump files with INSERT batching optimization
  - Supports `.sql`, `.sql.gz`, and `.sql.bz2` input files
  - Profile-based batch sizing: conservative (2,000) / aggressive (5,000)
  - Custom `--batch-size` override option
  - Progress reporting with time-based updates (every 2 seconds)
  - Statistics tracking: lines processed, queries written, INSERTs batched, reduction ratio
  - Automatic cleanup of partial output on error

- **CLI Service Architecture**: New modular CLI services for dump optimization
  - `CliOptimizerService.php`: Orchestration layer coordinating reader → parser → batcher → writer
  - `CliFileReader.php`: File reading abstraction supporting plain SQL, gzip, and bzip2
  - `CliSqlParser.php`: Lightweight SQL parser optimized for CLI streaming

- **CLI Test Suite**: Comprehensive test coverage for CLI functionality
  - `CliArgumentTest.php`: Argument parsing and validation
  - `CliFileReaderTest.php`: File reading across formats
  - `CliSqlParserTest.php`: SQL parsing correctness
  - `CliOptimizerServiceTest.php`: End-to-end optimization tests
  - `CliProgressTest.php`: Progress reporting validation
  - `CliErrorHandlingTest.php`: Error scenarios and cleanup
  - `CliIntegrationTest.php`: Full integration tests

### Changed in 2.22

- **JSON-Only Responses**: Removed legacy XML response code (closes #29)
  - All AJAX responses now use JSON format exclusively
  - Removed ~182 lines of dead XML handling code
  - Simplified `AjaxService` by removing `createXmlResponse()` and related methods
  - Cleaned up `BigDumpController` XML response branches
  - Updated `Request` class to remove XML content-type detection

### Documentation in 2.22

- **README.md**: Updated with CLI optimizer documentation
  - Usage examples for CLI tool
  - Profile explanations (conservative vs aggressive)
  - Exit codes documentation

### CLI Usage Examples

```bash
# Basic usage
php cli.php dump.sql -o optimized.sql

# With gzip input
php cli.php dump.sql.gz --output optimized.sql --batch-size=5000

# Aggressive profile with force overwrite
php cli.php backup.sql.bz2 -o backup_batched.sql --profile=aggressive -f
```

### CLI Profile Defaults

| Profile | Batch Size | Max Batch Bytes |
|---------|------------|-----------------|
| conservative | 2,000 | 16MB |
| aggressive | 5,000 | 32MB |

### Files Added in 2.22

| File | Purpose |
|------|---------|
| `cli.php` | CLI entry point with argument parsing |
| `src/Services/CliOptimizerService.php` | Optimization orchestration |
| `src/Services/CliFileReader.php` | Multi-format file reading |
| `src/Services/CliSqlParser.php` | Streaming SQL parser |
| `tests/CliArgumentTest.php` | Argument parsing tests |
| `tests/CliFileReaderTest.php` | File reader tests |
| `tests/CliSqlParserTest.php` | SQL parser tests |
| `tests/CliOptimizerServiceTest.php` | Optimizer service tests |
| `tests/CliProgressTest.php` | Progress reporting tests |
| `tests/CliErrorHandlingTest.php` | Error handling tests |
| `tests/CliIntegrationTest.php` | Integration tests |

### Files Modified in 2.22

| File | Change |
|------|--------|
| `src/Services/AjaxService.php` | Removed XML response methods (-135 lines) |
| `src/Controllers/BigDumpController.php` | Removed XML response handling (-48 lines) |
| `src/Core/Application.php` | Removed XML route registration |
| `src/Core/Request.php` | Removed XML content-type detection |
| `README.md` | Added CLI documentation section |

### Exit Codes (CLI)

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | User error (invalid arguments, file not found) |
| 2 | Runtime error (processing failure) |

---

## [2.21] - 2025-12-27 - BZ2 Compression Support

### Added in 2.21

- **BZ2 (bzip2) Compressed File Support**: Import `.bz2` and `.sql.bz2` files alongside existing `.gz` support
  - Extension-based detection using case-insensitive matching
  - Uses PHP's `bzopen()`, `bzread()`, `bzclose()` functions
  - Graceful fallback when PHP ext-bz2 is not installed
  - Purple "BZ2" badge in file listing (same color as GZip)

- **BZ2 Seek Workaround (ADR-001)**: Full resume functionality for interrupted BZ2 imports
  - PHP's bz2 extension lacks `bzseek()` unlike gzip's `gzseek()`
  - Implemented re-read strategy: close stream → reopen → read to target position
  - Optional progress callback for seek status display
  - Trade-off: O(n) resume time, acceptable for large files typically FTP-uploaded

- **BZ2 Extension Availability Check**: Conditional support based on PHP configuration
  - New `Config::isBz2Supported()` static method with result caching
  - `.bz2` files hidden from listing when ext-bz2 unavailable
  - Frontend validation via `data-bz2-supported` attribute
  - Custom error message: "BZ2 files require the PHP bz2 extension which is not installed"

- **BZ2 Test Suite**: Comprehensive test coverage for BZ2 functionality
  - `FileHandlerBz2Test.php`: 6 tests for file handling
  - `ConfigBz2Test.php`: 4 tests for configuration
  - `FrontendBz2Test.php`: 4 tests for UI/JavaScript
  - `Bz2IntegrationTest.php`: 10 tests for integration and edge cases
  - Test fixture: `tests/fixtures/test_bz2_import.sql.bz2`

### Changed in 2.21

- **allowed_extensions**: Updated from `['sql', 'gz', 'csv']` to `['sql', 'gz', 'bz2', 'csv']`
- **FileHandler**: Added `$bz2Mode` property mirroring `$gzipMode` pattern
- **home.php**: Added `data-bz2-supported` attribute and BZ2 badge case
- **fileupload.js**: Conditional BZ2 validation based on extension availability

### Technical Notes

**ADR-001: BZ2 Seek Workaround**

The BZ2 resume implementation differs from GZip due to PHP limitations:

```php
// GZip: Direct seek supported
gzseek($handle, $offset);

// BZ2: Re-read strategy required
bzclose($handle);
$handle = bzopen($filepath, 'r');
while ($bytesRead < $offset) {
    $chunk = bzread($handle, min($remaining, $bufferSize));
    $bytesRead += strlen($chunk);
}
```

**Performance Implications:**
- Resume from 500MB position in 1GB file: ~500MB re-read required
- Acceptable trade-off: preserves BigDump's critical resume feature
- Large BZ2 files typically uploaded via FTP, not browser

### Files Modified in 2.21

| File | Change |
|------|--------|
| `src/Models/FileHandler.php` | Added $bz2Mode, isBz2Mode(), seekBz2() workaround, bzopen/bzread/bzclose |
| `src/Config/Config.php` | Added 'bz2' to allowed_extensions, isBz2Supported() with caching |
| `templates/home.php` | Added data-bz2-supported, BZ2 badge with purple color |
| `assets/js/fileupload.js` | Conditional bz2 validation, custom error message |
| `assets/src/js/fileupload.js` | Source version with same changes |

### Test Files Created in 2.21

| File | Purpose |
|------|---------|
| `tests/FileHandlerBz2Test.php` | BZ2 file handling tests |
| `tests/ConfigBz2Test.php` | Config and extension tests |
| `tests/FrontendBz2Test.php` | Frontend functionality tests |
| `tests/Bz2IntegrationTest.php` | Integration and edge case tests |
| `tests/fixtures/test_bz2_import.sql.bz2` | Compressed SQL test fixture |

---

## [2.20] - 2025-12-27 - SSE Reliability & Session Handling

### Fixed in 2.20

- **SSE Session Locking**: Fixed critical issue where SSE connections could be blocked by PHP session locks
  - Added `session_write_close()` before rendering import page to release lock early
  - SSE endpoint now properly releases session lock before streaming
  - Prevents "Connecting..." modal from staying stuck indefinitely

- **SSE Retry Mechanism**: Added automatic retry with exponential backoff for timing issues
  - If "No active import session" error occurs, JavaScript retries automatically
  - Backoff: 100ms → 200ms → 400ms → 800ms → 1600ms (max 5 attempts)
  - Handles race conditions between page load and SSE connection

- **SSE Output Buffering**: Fixed PHP output buffering order in `SseService`
  - `ini_set('output_buffering', 'Off')` now called BEFORE `ob_end_clean()`
  - Prevents PHP from recreating buffers after clearing them
  - Added ~8KB padding to force Apache/mod_fcgid buffer flush

### Server Configuration Notes

If the "Connecting..." modal stays stuck, check your server configuration:

| Server | Configuration |
|--------|---------------|
| **Apache + mod_fcgid** | Add `FcgidOutputBufferSize 0` to disable buffering |
| **Apache + mod_proxy_fcgi** | Add `ProxyPassReverse` with `flushpackets=on` |
| **nginx** | Add `proxy_buffering off;` and `fastcgi_buffering off;` |
| **PHP built-in server** | Works without configuration (`php -S localhost:8000`) |

### Documentation Added in 2.20

- **README.md**: New "How It Works" section explaining:
  - Staggered import behavior (progress in steps is normal)
  - SSE real-time progress mechanism
- **README.md**: Enhanced Troubleshooting section with:
  - Laragon-specific configuration path
  - Quick diagnostic command (`php -S`)
  - Clearer explanations of SSE buffering issues

### Files Modified in 2.20

| File | Change |
|------|--------|
| `src/Services/SseService.php` | Fixed output buffering order, added padding |
| `src/Services/AjaxService.php` | Added SSE retry with exponential backoff |
| `src/Controllers/BigDumpController.php` | Added `session_write_close()` in import flow |
| `.htaccess` | Added `SetEnvIf` to disable gzip for SSE requests |
| `README.md` | Added "How It Works" section, enhanced Troubleshooting |
| `docs/CHANGELOG.md` | Documented v2.20 changes |

---

## [2.19] - 2025-12-26 - Performance Profile System

### Added in 2.19

- **Performance Profile System**: Choose between `conservative` (default) and `aggressive` modes
  - Conservative: Optimized for shared hosting (64MB memory limit)
  - Aggressive: Higher throughput for dedicated servers (128MB+ required)
- **Configurable INSERT Batch Sizes**: 2,000 (conservative) / 5,000 (aggressive)
- **Configurable File Buffer**: 64KB-256KB based on file category
- **INSERT IGNORE Batching**: Now properly batches INSERT IGNORE statements
- **Adaptive Batch Sizing**: Automatically adjusts based on average row size
- **Memory Caching**: Reduced overhead from memory_get_usage() calls
- **Configurable COMMIT Frequency**: Every batch (conservative) / every 3 batches (aggressive)

### Changed in 2.19

- **AutoTuner Profile-Aware**: Now supports profile-based multiplier and safety margins
  - Aggressive: 1.3x batch reference multiplier, 70% safety margin, 2M max batch
  - Conservative: 1.0x multiplier, 80% safety margin, 1.5M max batch
- **System Resources Cached**: 60-second TTL for system detection
- **Memory Checks Cached**: 1-second TTL reduces redundant memory_get_usage() calls

### Config Options in 2.19

| Option | Conservative | Aggressive |
|--------|-------------|------------|
| `performance_profile` | `'conservative'` | `'aggressive'` |
| `file_buffer_size` | 64KB | 128KB |
| `insert_batch_size` | 2,000 | 5,000 |
| `max_batch_bytes` | 16MB | 32MB |
| `commit_frequency` | 1 | 3 |

### Performance Targets

- **Conservative**: Baseline performance, <64MB memory
- **Aggressive**: +20-30% throughput, <128MB memory

### Files Modified in 2.19

| File | Change |
|------|--------|
| `src/Config/Config.php` | Profile system, validation, profile-dependent defaults |
| `src/Services/InsertBatcherService.php` | Configurable batch limits, INSERT IGNORE, adaptive sizing |
| `src/Models/FileHandler.php` | Configurable buffer size, category-based adjustment |
| `src/Services/AutoTunerService.php` | Profile-aware thresholds, resource caching |
| `src/Services/ImportService.php` | Profile-based config, commit frequency |
| `config/config.example.php` | New performance options documented |

---

## [2.18] - 2025-12-24 - UI Improvements & URL Compatibility

### Added in 2.18

- **Total Lines in Preview**: Preview modal now shows exact total line count
  - Supports both gzip-compressed and regular SQL files
  - Formatted with locale-aware number display

### Changed in 2.18

- **URL Pattern Migration**: Replaced clean URLs with action parameters for Apache compatibility
  - `/import/sse` → `?action=sse_import`
  - `/import/stop` → `?action=stop_import`
  - `/import/start` → `?action=start_import`
  - All other routes follow `?action=xxx` pattern
  - Fixes SSE connection issues on servers without mod_rewrite

### Fixed in 2.18

- **Button Icon Sizing**: Fixed inconsistent button icon sizes (eye/view button)
  - Added `min-height` and `min-width` to `.btn-icon` class
- **Smart Error Handling**: Improved CREATE TABLE error detection and reporting

---

## [2.17] - 2025-12-24 - Performance Optimizations & Import Speedup

### Added in 2.17

- **MySQL Pre-queries**: Added default performance-boosting queries at import start
  - `SET autocommit = 0`
  - `SET unique_checks = 0`
  - `SET foreign_key_checks = 0`
  - `SET sql_log_bin = 0`
  - Post-queries automatically restore settings after import

### Changed in 2.17

- **SqlParser Optimization**: Replaced character-by-character iteration with `strpos()` for O(1) jumps instead of O(n) scanning
- **InsertBatcherService**: Substituted complex regex patterns with efficient string functions for faster batch building
- **FileHandler Buffering**: Implemented 64KB buffered reading to reduce system calls and improve I/O performance
- **Buffer Handling Fixes**: Fixed `tell()`, `seek()`, and `eof()` methods for proper buffer management

### Maintenance in 2.17

- **Line Ending Normalization**: Standardized all 57 source files to Unix line endings (LF)
- **Gitignore Update**: Added `uploads/.import_history.json` to prevent local dev data commits

### Files Modified in 2.17

| File | Change |
|------|--------|
| `src/Models/SqlParser.php` | Optimized with strpos() instead of char iteration |
| `src/Services/InsertBatcherService.php` | String functions instead of regex |
| `src/Models/FileHandler.php` | 64KB buffered reading, fixed buffer methods |
| `config/config.example.php` | Added pre/post queries defaults |

---

## [2.16] - 2025-12-12 - File-Aware Auto-Tuning

### Added in 2.16

- **File-Aware Auto-Tuning**: Intelligent batch sizing based on file size category
  - Analyzes SQL dump at import start (1MB sample)
  - Detects file size category: tiny/small/medium/large/massive
  - Detects bulk INSERT patterns for optimized processing
  - RAM x File Size reference matrix for optimal batch sizes
- **Dynamic Batch Adaptation**: Real-time batch size adjustment during import
  - Tracks speed and memory history (last 5 samples)
  - Increases batch 1.5x when RAM <30% and speed stable
  - Decreases batch 0.7x when RAM >70%
  - Decreases batch 0.8x on >30% speed degradation
- **New FileAnalysisService**: Dedicated service for file analysis
  - Category detection: tiny (<10MB), small (<50MB), medium (<500MB), large (<2GB), massive (2GB+)
  - Bulk INSERT detection via regex pattern matching
  - Average bytes per line calculation for accurate estimates
- **Enhanced Dashboard Metrics**:
  - File category badge (color-coded)
  - Bulk INSERT indicator (+B)
  - Memory vs Target display (e.g., "45% / 60% target")
  - Speed trend indicator (↑/↓/→)

### Changed in 2.16

- **AutoTunerService refactored**: Now supports file-aware calculation alongside RAM-only fallback
  - New BATCH_REFERENCE constant: RAM x FileCategory matrix
  - New `adaptBatchSize()` method with speed/memory history tracking
  - New `getSpeedTrend()` method for UI
- **ImportSession extended**: Added `fileAnalysisData` field for session persistence
- **ImportService extended**: Added `analyzeFile()` and `restoreFileAnalysis()` methods

### New Config Options in 2.16

| Option | Default | Description |
|--------|---------|-------------|
| `file_aware_tuning` | `true` | Enable file-aware auto-tuning |
| `sample_size_bytes` | `1048576` | File sample size for analysis (1MB) |
| `min_dynamic_batch` | `50000` | Minimum batch during dynamic adaptation |

### Performance Impact

| File Size | RAM 5GB (Before) | RAM 5GB (After) | Improvement |
|-----------|------------------|-----------------|-------------|
| <50MB | 380k | 100k | Optimized (smaller = faster) |
| 50-500MB | 380k | 200k | Better RAM usage |
| 500MB-2GB | 380k | 350k | ~same |
| >2GB | 380k | 500k | **+32%** faster |

### Files Modified in 2.16

| File | Change |
|------|--------|
| `src/Services/FileAnalysisService.php` | **NEW** - File analysis and categorization |
| `src/Services/AutoTunerService.php` | Added BATCH_REFERENCE, file-aware calculation, dynamic adaptation |
| `src/Services/ImportService.php` | Integrated file analysis, added analyzeFile/restoreFileAnalysis |
| `src/Models/ImportSession.php` | Added fileAnalysisData field and methods |
| `src/Controllers/BigDumpController.php` | Call analyzeFile at import start |
| `templates/import.php` | Enhanced Performance section with new metrics |
| `config/config.example.php` | Added file-aware tuning options |

---

## [2.15] - 2025-12-14 - Elapsed Timer & SQL Safety

### Added in 2.15

- **Elapsed Timer**: Real-time HH:MM:SS timer during import
  - Synchronized with SSE connection lifecycle
  - Starts on import begin, stops on complete/error/disconnect
  - Client-side JavaScript with 1-second precision
- **Progress Display**: Percentage shown next to elapsed timer
  - Cleaner UI with dedicated display area
  - Progress bar simplified (no text overlay)

### Fixed in 2.15

- **SQL Validation**: Pending queries validated before execution
  - Prevents execution of corrupted/invalid SQL data
  - Validates against SQL keyword whitelist (INSERT, UPDATE, DELETE, etc.)
  - Invalid queries logged and discarded
- **InsertBatcher Safety**: Exception thrown when building batch without prefix
  - Prevents generation of invalid multi-value INSERT statements
  - Clear error message with query preview for debugging

### Changed in 2.15

- Progress bar no longer displays percentage text (moved to dedicated area)
- Percentage formatting: 1 decimal in stat box, 2 decimals in header display
- Timer lifecycle integrated with SSE error handling and reconnection

---

## [2.14] - 2025-12-12 - Zero-CDN Asset Pipeline

### Added in 2.14

- **GitHub Actions Build Pipeline**: Automated asset compilation on push/PR
  - Tailwind CSS purging with standalone CLI (no Node.js required)
  - JavaScript minification with esbuild
  - Auto-commit built assets to repository
  - PR validation builds (no commit)
- **SVG Icon Sprite**: Font Awesome icons replaced with optimized SVG sprite
  - 13 icons: sun, moon, eye, play, trash, xmark, clock-rotate-left, circle-check, circle-xmark, circle-exclamation, code, database, spinner
  - Single `assets/icons.svg` file (~3KB)
  - Tailwind `animate-spin` for spinner animation
- **New Asset Structure**:
  - `assets/src/css/tailwind.css` - Source CSS with Tailwind directives
  - `assets/src/js/*.js` - Source JavaScript files (6 files)
  - `assets/dist/*.min.css` - Compiled and minified CSS
  - `assets/dist/*.min.js` - Compiled and minified JavaScript
  - `scripts/generate-icons.php` - SVG sprite generator

### Changed in 2.14

- **CDN Removal**: Eliminated all external CDN dependencies
  - Removed Tailwind CSS CDN (~300KB)
  - Removed Font Awesome CDN (~100KB)
  - Self-hosted, purged assets only
- **Asset Size Optimization**:
  - Before: ~454KB (CDNs + unminified)
  - After: ~47KB (purged + minified)
  - **90% reduction** in asset size
- **Icon Rendering**: All Font Awesome `<i>` tags replaced with SVG `<use>` elements
  - Templates: layout.php, home.php
  - JavaScript: history.js, filepolling.js (dynamic icon creation)

### New Files in 2.14

| File | Purpose |
|------|---------|
| `assets/src/css/tailwind.css` | Tailwind v4 CSS-first config + custom styles |
| `assets/icons.svg` | SVG icon sprite (13 icons) |
| `scripts/generate-icons.php` | PHP script to generate icon sprite |
| `.github/workflows/build-assets.yml` | CI workflow for asset builds |

### Asset Sizes in 2.14

| Asset | Size |
|-------|------|
| app.min.css | 17KB |
| bigdump.min.js | 767B |
| filepolling.min.js | 5.7KB |
| fileupload.min.js | 6.2KB |
| history.min.js | 3.5KB |
| modal.min.js | 3.1KB |
| preview.min.js | 4.3KB |
| icons.svg | 6.3KB |
| **TOTAL** | **~47KB** |

---

## [2.13] - 2025-12-10 - CSS Components & Accessibility

### Added in 2.13

- **CSS Component System**: Comprehensive reusable class library
  - Button system: `.btn` base with color variants (blue, green, red, amber, cyan, purple, indigo, gray)
  - Alert system: `.alert` with status variants (success, error, warning, info)
  - Card system: `.card`, `.stat-box`, `.info-box`, `.metric-box` with child classes
  - Table styling: `.table` with header, zebra stripes, hover states
  - Additional components: `.code`, `.badge`, `.modal-*`, `.dropzone`, `.progress`, `.spinner`, `.tooltip-*`
  - Full dark mode support via `[data-theme="dark"]` selectors
- **JavaScript Modularization**: Split monolithic home.php JS into modules
  - `preview.js` (196 lines) - SQL preview modal functionality
  - `history.js` (149 lines) - Import history modal
  - `filepolling.js` (371 lines) - Real-time file list updates
  - `modal.js` (260 lines) - Focus trap and keyboard handling
  - IIFE pattern with `window.BigDump` namespace
  - PHP config via `data-*` attributes (no inline PHP in JS)
- **Accessibility (WCAG 2.1 AA)**:
  - Focus-visible styles for modal close buttons, tabs, links, dropzone, details/summary
  - ARIA attributes on modals: `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`
  - `aria-label` on close buttons, `aria-hidden` on decorative icons
  - Focus trap in modals (Tab cycles within modal)
  - ESC key to close modals
  - Focus management: focus first element on open, restore focus on close
  - Amber focus ring on blue buttons for better contrast

### Changed in 2.13

- **Template Refactoring**: Inline Tailwind classes replaced with component classes
  - Button classes reduced from ~150 chars to ~14 chars per instance
  - ~3000 characters reduction in home.php alone
  - Dark mode centralized in CSS instead of repeated `dark:` classes
- **CSS Growth**: `bigdump.css` expanded from 237 to 937 lines (component library)
- **home.php Reduction**: 919 → 386 lines after JS extraction (-533 lines)

### Files Added in 2.13

| File | Lines | Purpose |
|------|-------|---------|
| `assets/js/preview.js` | 196 | SQL preview modal |
| `assets/js/history.js` | 149 | Import history modal |
| `assets/js/filepolling.js` | 371 | Real-time file list polling |
| `assets/js/modal.js` | 260 | Focus trap & keyboard handling |

---

## [2.12] - 2025-12-11 - Real-time File List Refresh

### Changed in 2.12

- **Upload Completion**: File list now refreshes without full page reload
  - Uses `refreshFileList()` for seamless UI update after upload
  - Clears upload UI state with `clearAll()`
  - Reduced feedback delay from 1000ms to 500ms
  - Fallback to `location.reload()` if polling unavailable

### New Routes in 2.12

- `?action=files_list` - Get file list as JSON for AJAX refresh

---

## [2.11] - 2025-12-09 - Quick Wins: Animations, Preview & History

### Added in 2.11

- **SQL Preview Modal**: Preview file contents before importing
  - Click the eye icon on any file to open preview
  - Raw SQL content display (first 50 lines)
  - Extracted queries with type badges (CREATE, INSERT, DROP, UPDATE, etc.)
  - Tabbed interface: Raw Content / Queries
  - File info: size, type, lines count, queries count
  - Direct "Start Import" button from preview
  - Supports both `.sql` and `.gz` files
- **Import History**: Track all import operations
  - New `ImportHistoryService` for persistent logging
  - History modal with statistics (total, successful, failed, queries)
  - Table view with status icons, dates, and results
  - Clear history functionality
  - JSON storage in `uploads/.import_history.json`
  - Automatic logging at end of SSE imports
- **UI Animations**: Enhanced visual feedback
  - Buttons: `hover:scale-105`, `active:scale-95`, shadow on hover
  - Progress bar: Animated striped pattern
  - Stat boxes: Lift effect on hover (`-translate-y-1`)
  - Dropzone: Scale and shadow on hover
  - Table rows: Smooth color transitions

### Changed in 2.11

- **Tailwind Config**: Added custom `progress-stripe` animation keyframes
- **Button Classes**: Unified animation classes across all buttons
- **Security**: All dynamic content uses safe DOM methods (`textContent`, `createElement`)

### New Routes in 2.11

- `?action=preview&fn=filename` - Get JSON preview data for a file
- `?action=history` - Get import history and statistics
- `?action=history&do=clear` - Clear all history
- `?action=history&do=stats` - Get statistics only

---

## [2.10] - 2025-12-09 - Tailwind CSS Migration

### Added in 2.10

- **Tailwind CSS Framework**: Complete migration from Bootstrap/custom CSS to Tailwind CDN
  - Dark mode support via `data-theme` attribute
  - Custom container width (`max-w-container: 70vw`)
  - Responsive grid layouts
- **SSE Loading Overlay**: "Connecting..." spinner while establishing SSE connection
  - Displayed only during active imports
  - Auto-hidden on first progress event
- **SSE Error Display**: Import errors now displayed inline with Tailwind styling
  - Full error details in collapsible section
  - "Drop & Restart" button for "Table already exists" errors
  - Proper dark mode support

### Changed in 2.10

- **Header Layout**: Logo, title, and dark mode toggle now aligned with main content container
  - Gradient background remains full-width
  - Content constrained to `max-w-container`
- **CSS Reduction**: Removed ~1100 lines of Bootstrap/custom CSS
  - Only animations, tooltips, and specific styles remain in `bigdump.css`
- **JavaScript Selectors**: Updated to use Tailwind-compatible selectors
  - `displayErrorInPage()` uses `main` instead of `.card-body`
  - Stats boxes use `.stat-box .stat-value` classes
  - Progress bar uses `.progress-bar` class

### Fixed in 2.10

- **Real-time Stats Updates**: Added missing CSS classes (`stat-box`, `stat-value`, `progress-bar`) for JavaScript compatibility
- **SSE Error Handling**: Errors now display in page instead of failing silently
- **Dark Mode Consistency**: All components properly styled for both light and dark themes

### Templates Refactored in 2.10

- `templates/layout.php` - Tailwind base layout with dark mode
- `templates/home.php` - File list, upload zone, cards
- `templates/import.php` - Progress, stats, error display
- `templates/error.php` - Error page styling

---

## [2.9] - 2025-12-08 - UI Overhaul & Drop Table Recovery

### Added in 2.9

- **Drop Table & Restart Feature**: One-click recovery from "Table already exists" errors
  - New route `/import/drop-restart?table=xxx&fn=file.sql`
  - Safe table name validation (alphanumeric + underscore only)
  - Confirmation dialog before DROP TABLE execution
  - Automatic import restart after table deletion
- **Enhanced Error Page**: Contextual help for common errors
  - "No filename specified" error: session expiry explanation + recovery steps
  - "File not found" error: troubleshooting checklist
  - Info boxes with icons and actionable instructions

### Changed in 2.9

- **Button Design Overhaul**: Tailwind-inspired gradients with pulse animations
  - Primary: blue gradient (#1e40af → #1e3a8a)
  - Danger: red gradient (#f87171 → #ef4444)
  - Success: green gradient (#22c55e → #16a34a)
  - Warning: amber gradient (#f59e0b → #d97706)
  - Info: sky gradient (#0284c7 → #0369a1)
  - Secondary: zinc gradient (#52525b → #3f3f46)
- **Hover Animation**: Pulse effect with growing box-shadow (1.5s infinite)
- **Button Colors**: All buttons now use white text for consistency
- **Import Button**: Changed from blue (primary) to green (success) on home page
- **Back to Home**: Changed from gray (secondary) to sky blue (info)
- **Button Labels**: "Start Over" renamed to "Start Over (resume)"
- **Layout**: "Processing" header moved to top of import page
- **Button Alignment**: Flexbox layout for horizontal button groups

### Fixed in 2.9

- **Stop Import Error**: Fixed permission denied on Windows when manipulating session files
  - Added `is_readable()` check before file operations
  - Added try/catch with error suppression for session file access
- **Text Visibility**: Contextual `.text-muted` color (gray in cards, white-70% in footer)
- **Button Font Size**: Standardized 14px across all button types (button/anchor)

### CSS Additions in 2.9

```css
/* Pulse animations for each button type */
@keyframes pulse-primary { /* blue shadow */ }
@keyframes pulse-danger { /* red shadow */ }
@keyframes pulse-success { /* green shadow */ }
@keyframes pulse-warning { /* amber shadow */ }
@keyframes pulse-info { /* sky shadow */ }
@keyframes pulse-secondary { /* zinc shadow */ }
```

---

## [2.8] - 2025-12-08 - Session Persistence & Aggressive Auto-Tuning

### Added in 2.8

- **Server-Side Session Persistence**: Import state migrated from URL params to `$_SESSION`
  - Crash-resilient imports: resume after browser refresh or server restart
  - New `ImportSession::toSession()`, `fromSession()`, `clearSession()` methods
  - Direct session file writing for SSE compatibility
- **SSE (Server-Sent Events) Streaming**: Real-time progress without polling
  - New `SseService.php` for event stream handling
  - Automatic reconnection after connection loss
  - Seamless fallback to AJAX mode
- **Smooth Transition Engine**: Fluid counter animations in UI
  - "Roulette" effect for number transitions
  - Configurable easing factor (0.06 default)
- **Loading Overlay**: Visual feedback on import start
  - Spinner animation with dynamic file name display
- **Frozen Estimates**: Lock total estimates after 5% progress
  - Prevents fluctuating Lines/Queries totals during import
  - `frozenLinesTotal` and `frozenQueriesTotal` properties

### Changed in 2.8

- **Ultra-Aggressive RAM Profiles**: Granular per-GB batch sizing
  - < 1 GB: 80,000 lines (was 30,000)
  - < 4 GB: 300,000 lines (was 100,000)
  - < 8 GB: 620,000 lines (was 200,000)
  - < 12 GB: 940,000 lines (NEW)
  - < 16 GB: 1,260,000 lines (NEW)
  - > 16 GB: 1,500,000 lines (NEW)
- **AutoTuner Aggressiveness**: 80% safety margin (was 50%), 150 bytes/line (was 300)
- **Default Batch Size**: 50,000 lines (was 3,000)
- **Max Batch Size**: 1,500,000 lines (was 100,000)

### Fixed in 2.8

- **Absolute URL Redirect**: Fixed `https://import/` redirect bug when app at root
- **Corrupted PendingQuery**: SQL regex validation prevents invalid query restoration
- **Fluctuating Totals**: Frozen estimates after 5% progress

### Refactored in 2.8

- Templates moved from `src/Views/` to `templates/`
- Entry point moved from `public/index.php` to `index.php`
- Removed `.pending_*.tmp` files (replaced by session storage)
- Added `.htaccess` for clean URL routing
- Static assets organized in `assets/`

### Configuration changes in 2.8

```php
// AutoTuner now scales to 1.5M lines/batch for 16GB+ systems
'min_batch_size' => 10000,      // Was 3000
'max_batch_size' => 1500000,    // Was 100000
'linespersession' => 50000,     // Was 3000
```

---

## [2.7] - 2025-12-07 - Post-Queries & NVMe Optimization

### Added in 2.7

- **Post-queries Support**: Restore database constraints after import completion
  - New `post_queries` config option for constraint restoration
  - `Database::executePostQueries()` method
  - Auto-COMMIT at each session end (critical with `autocommit=0`)
  - Restores `autocommit`, `unique_checks`, `foreign_key_checks` automatically
- **Byte-based Batch Limit**: 16MB safety limit per INSERT batch
  - Respects MySQL `max_allowed_packet` setting
  - Prevents oversized queries from failing

### Changed in 2.7

- **NVMe/SSD Optimizations**: Aggressive RAM profiles for modern storage
  - < 512 MB: 5,000 → 10,000 lines
  - < 1 GB: 15,000 → 30,000 lines
  - < 2 GB: 30,000 → 60,000 lines
  - < 4 GB: 50,000 → 100,000 lines
  - **New** < 8 GB: 150,000 lines
  - > 8 GB: 80,000 → 200,000 lines
- **AutoTuner Aggressiveness**: 50% safety margin (was 70%), 300 bytes/line estimate (was 500)
- **Default insert_batch_size**: 1,000 → 10,000 (10x larger batches)
- **max_query_memory**: 10 MB → 100 MB for high-speed imports
- **Pre-queries**: Added `SET sql_log_bin = 0` for binary logging bypass
- **Progress Precision**: 2 decimal places (was integer) across all views

### Configuration added by 2.7

```php
'insert_batch_size' => 10000,     // Group INSERTs (was 1000)
'max_batch_size' => 300000,       // NVMe ceiling (was 100000)
'max_query_memory' => 104857600,  // 100 MB (was 10 MB)
'pre_queries' => [
    'SET autocommit = 0',
    'SET unique_checks = 0',
    'SET foreign_key_checks = 0',
    'SET sql_log_bin = 0',        // NEW
],
'post_queries' => [               // NEW
    'COMMIT',
    'SET autocommit = 1',
    'SET unique_checks = 1',
    'SET foreign_key_checks = 1',
],
```

---

## [2.6] - 2025-12-07 - INSERT Batching & Statistics Estimation

### Added in 2.6

- **INSERT Batching (x10-50 speedup)**: Groups consecutive simple INSERTs into multi-value queries
  - New `InsertBatcherService.php` transforms individual INSERTs into batched queries
  - `insert_batch_size` config option (default: 1000)
  - Automatic detection of compatible INSERT statements
  - Seamless integration with existing import flow
- **Force Batch Size Option**: Override auto-tuning with specific batch size
  - `force_batch_size` config option bypasses RAM-based calculations
  - Useful for known server capacity or testing
- **Statistics Estimation**: Real-time estimates during import
  - Lines/queries remaining estimated from bytes/line ratio
  - Displayed with "~" prefix to indicate estimation
  - Exact values shown upon completion

### Changed in 2.6

- **Increased Batch Size Profiles**: More aggressive defaults for modern servers
  - < 512 MB: 3,000 → 5,000
  - < 1 GB: 8,000 → 15,000
  - < 2 GB: 15,000 → 30,000
  - < 4 GB: 25,000 → 50,000
  - > 4 GB: 40,000 → 80,000
- **Max Batch Size**: Increased from 50,000 to 100,000
- **Pre-queries**: Now enabled by default in config template

### Fixed in 2.6

- **Stale Pending Query Bug**: Fixed corruption when `foffset=0` with existing pending file
  - Fresh imports now delete stale `.pending_*.tmp` files
  - Prevents SQL fragment merging from previous aborted imports

### Configuration added by 2.6

```php
'insert_batch_size' => 1000,   // Group INSERTs (0 = disabled)
'force_batch_size' => 0,       // Override auto-tuning (0 = auto)
'pre_queries' => [
    'SET autocommit = 0',
    'SET unique_checks = 0',
    'SET foreign_key_checks = 0',
],
```

---

## [2.5] - 2025-12-06 - Auto-Tuning & SQL Parser Fixes

### Added in 2.5

- **Auto-Tuning Performance System**: Dynamic batch size based on available system RAM
  - Automatic RAM detection: Windows COM/WMI, Linux `/proc/meminfo`, smart fallbacks
  - RAM-based batch sizing (3,000 to 50,000 lines per session)
  - Real-time memory pressure monitoring with adaptive adjustments
  - New `AutoTunerService.php` for performance optimization
- **Performance Monitoring UI**: Real-time metrics display during import
  - System/OS detection
  - Available RAM display
  - Current batch size (auto-calculated)
  - Memory usage percentage
  - Import speed (lines/sec)
  - Batch adjustment notifications

### Fixed in 2.5

- **SQL Parser Dual Quote Support**: Parser now correctly handles both single (`'`) and double (`"`) quotes
  - Prevents query fusion when double-quoted strings appear in SQL
  - Cross-session persistence of active quote state
- **Multi-line INSERT Session Persistence**: Extended INSERT statements no longer corrupt across AJAX sessions
  - Replaced unreliable PHP `$_SESSION` with file-based storage for pending queries
  - Pending queries stored in `uploads/.pending_*.tmp` files
  - Automatic cleanup on import completion or error
  - Fixes "SET SQL_MODE" appearing inside INSERT VALUES error

### Configuration added by 2.5

```php
'auto_tuning' => true,        // Enable dynamic batch sizing
'min_batch_size' => 3000,     // Minimum lines per session
'max_batch_size' => 50000,    // Maximum lines per session
```

---

## [2.4] - 2025-12-06 - Session Cleanup & URI Fix

### Fixed in 2.4

- **Clear Session on New Import**: Previous import state no longer contaminates new imports
  - Session cleanup when starting import of the same file
  - Prevents SQL statement merging from aborted imports
- **Statistics Table Display**: AJAX JavaScript correctly updates all statistics cells
  - Fixed cell targeting in table structure
  - Proper handling of label/value columns
  - Progress bar percentage now updates correctly
- **414 URI Too Long Error**: Large pending queries no longer cause URL length issues
  - Moved `pendingQuery` storage from URL parameters to PHP `$_SESSION`
  - Session-isolated per file using hash-based keys
  - Automatic cleanup on import completion

### Changed in 2.4

- Minor design improvements to layout

---

## [2.3] - 2025-12-06 - Multi-line INSERT Persistence

### Added in 2.3

- **Multi-line INSERT Cross-Session State Persistence**
  - Extended INSERT statements spanning multiple lines now work across sessions
  - Parser state (`currentQuery`, `inString`) persisted between sessions
  - New properties in `ImportSession`: `pendingQuery`, `inString`
  - New methods in `SqlParser`: `setInString()`, `getCurrentQuery()`, `setCurrentQuery()`

### Data Flow added by 2.3

```text
Session N ends → save currentQuery + inString → URL params
Session N+1 starts → restore parser state → continue parsing
```

---

## [2.2] - 2025-12-06 - Error Display & Security

### Added in 2.2

- **Import Error Display Enhancement**
  - Improved error visualization during import process
  - Better error messages with context
- **Security Configuration**
  - Added `uploads/.htaccess` for directory protection
  - Moved `config.php` to `config.example.php` template

### Changed in 2.2

- Translated all French comments to English throughout codebase
- Updated footer branding

---

## [2.1] - 2025-12-06 - Drag & Drop Upload

### Added in 2.1

- **Modern Drag & Drop Upload Component**
  - Replaced legacy form with FileUpload component
  - Drag & drop zone with visual feedback (hover/active states)
  - Multi-file selection support
  - Individual progress bar per file with percentage display
  - Concurrent upload queue (max 2 simultaneous uploads)
  - Client-side validation for file type (`.sql`, `.gz`, `.csv`) and size
  - Visual status indicators: pending, uploading (spinner), success, error
  - Remove files from queue before upload
  - Auto-reload after successful uploads
- **Technical Implementation**
  - Pure vanilla JavaScript (ES6+), no dependencies
  - Safe DOM manipulation (createElement/textContent, no innerHTML)
  - XHR with progress events for real-time feedback
  - BEM CSS methodology for component styling
  - Responsive design with smooth transitions

### Documentation added by 2.1

- Added repository screenshot for presentation
- Translated README.md to English with w3spi5 branding

---

## [2.0] - 2025-12-06 - MVC Refactoring

### Added in 2.0

- **Complete MVC Refactoring** of original BigDump script
  - Object-oriented architecture
  - PSR-4 autoloading
  - Separation of concerns (Controllers, Models, Services, Views)

### Architecture at 2.0

```text
public/index.php → Core/Application → Core/Router → Controllers → Services
```

### Components added by 2.0

- `Core/Application.php` - Main orchestrator
- `Core/Router.php` - Action routing
- `Core/Request.php` - HTTP request wrapper
- `Core/Response.php` - HTTP response wrapper
- `Core/View.php` - Template engine
- `Controllers/BigDumpController.php` - Single controller
- `Models/Database.php` - MySQLi wrapper
- `Models/FileHandler.php` - File operations (gzip support, BOM handling)
- `Models/ImportSession.php` - Import state tracking
- `Models/SqlParser.php` - Stateful SQL parser
- `Services/ImportService.php` - Core import logic
- `Services/AjaxService.php` - AJAX response generation

### Features in 2.0

- Staggered import to bypass timeout limits
- Multi-format support: `.sql`, `.gz`, `.csv`
- AJAX mode (recommended)
- Modern responsive interface
- Enhanced security (path traversal, XSS protection)
- Clear error messages
- UTF-8 and BOM support

### Requirements for version 2.0

- PHP 8.1+
- MySQLi extension
- MySQL/MariaDB server
