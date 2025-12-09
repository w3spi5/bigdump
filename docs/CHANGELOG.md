# Changelog

All notable changes to BigDump are documented in this file.

## [2.11] - Quick Wins: Animations, Preview & History

### Added in 2.11

- **SQL Preview Modal**: Preview file contents before importing
  - Click the eye icon (👁️) on any file to open preview
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

## [2.10] - Tailwind CSS Migration

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

## [2.9] - UI Overhaul & Drop Table Recovery

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

## [2.8] - Session Persistence & Aggressive Auto-Tuning

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

## [2.7] - Post-Queries & NVMe Optimization

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

## [2.6] - INSERT Batching & Statistics Estimation

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

## [2.5] - Auto-Tuning & SQL Parser Fixes

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

## [2.4] - Session Cleanup & URI Fix

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

## [2.3] - Multi-line INSERT Persistence

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

## [2.2] - Error Display & Security

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

## [2.1] - Drag & Drop Upload

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

## [2.0] - MVC Refactoring

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
