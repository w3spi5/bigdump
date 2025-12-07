# Changelog

All notable changes to BigDump are documented in this file.

## [2.6.1] - 2025-12-07

### Added

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

### Changed

- **Increased Batch Size Profiles**: More aggressive defaults for modern servers
  - < 512 MB: 3,000 → 5,000
  - < 1 GB: 8,000 → 15,000
  - < 2 GB: 15,000 → 30,000
  - < 4 GB: 25,000 → 50,000
  - > 4 GB: 40,000 → 80,000
- **Max Batch Size**: Increased from 50,000 to 100,000
- **Pre-queries**: Now enabled by default in config template

### Fixed

- **Stale Pending Query Bug**: Fixed corruption when `foffset=0` with existing pending file
  - Fresh imports now delete stale `.pending_*.tmp` files
  - Prevents SQL fragment merging from previous aborted imports

### Configuration

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

## [2.6] - 2025-12-06

### Added

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

### Fixed

- **SQL Parser Dual Quote Support**: Parser now correctly handles both single (`'`) and double (`"`) quotes
  - Prevents query fusion when double-quoted strings appear in SQL
  - Cross-session persistence of active quote state
- **Multi-line INSERT Session Persistence**: Extended INSERT statements no longer corrupt across AJAX sessions
  - Replaced unreliable PHP `$_SESSION` with file-based storage for pending queries
  - Pending queries stored in `uploads/.pending_*.tmp` files
  - Automatic cleanup on import completion or error
  - Fixes "SET SQL_MODE" appearing inside INSERT VALUES error

### Configuration

```php
'auto_tuning' => true,        // Enable dynamic batch sizing
'min_batch_size' => 3000,     // Minimum lines per session
'max_batch_size' => 50000,    // Maximum lines per session
```

---

## [2.4] - 2025-12-06

### Fixed

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

### Changed

- Minor design improvements to layout

---

## [2.3] - 2025-12-06

### Added

- **Multi-line INSERT Cross-Session State Persistence**
  - Extended INSERT statements spanning multiple lines now work across sessions
  - Parser state (`currentQuery`, `inString`) persisted between sessions
  - New properties in `ImportSession`: `pendingQuery`, `inString`
  - New methods in `SqlParser`: `setInString()`, `getCurrentQuery()`, `setCurrentQuery()`

### Data Flow

```text
Session N ends → save currentQuery + inString → URL params
Session N+1 starts → restore parser state → continue parsing
```

---

## [2.2] - 2025-12-06

### Added

- **Import Error Display Enhancement**
  - Improved error visualization during import process
  - Better error messages with context
- **Security Configuration**
  - Added `uploads/.htaccess` for directory protection
  - Moved `config.php` to `config.example.php` template

### Changed

- Translated all French comments to English throughout codebase
- Updated footer branding

---

## [2.1] - 2025-12-06

### Added

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

### Documentation

- Added repository screenshot for presentation
- Translated README.md to English with w3spi5 branding

---

## [2.0] - 2025-12-06

### Added

- **Complete MVC Refactoring** of original BigDump script
  - Object-oriented architecture
  - PSR-4 autoloading
  - Separation of concerns (Controllers, Models, Services, Views)

### Architecture

```text
public/index.php → Core/Application → Core/Router → Controllers → Services
```

### Components

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

### Features

- Staggered import to bypass timeout limits
- Multi-format support: `.sql`, `.gz`, `.csv`
- AJAX mode (recommended)
- Modern responsive interface
- Enhanced security (path traversal, XSS protection)
- Clear error messages
- UTF-8 and BOM support

### Requirements

- PHP 8.1+
- MySQLi extension
- MySQL/MariaDB server
