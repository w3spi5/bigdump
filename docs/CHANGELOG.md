# Changelog

All notable changes to BigDump are documented in this file.

## [2.17] - Performance Optimizations & Import Speedup

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

## [2.16] - File-Aware Auto-Tuning

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
## [2.15] - Elapsed Timer & SQL Safety

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

## [2.14] - Zero-CDN Asset Pipeline

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

## [2.13] - CSS Components & Accessibility

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

## [2.12] - Real-time File List Refresh

### Changed in 2.12

- **Upload Completion**: File list now refreshes without full page reload
  - Uses `refreshFileList()` for seamless UI update after upload
  - Clears upload UI state with `clearAll()`
  - Reduced feedback delay from 1000ms to 500ms
  - Fallback to `location.reload()` if polling unavailable

### New Routes in 2.12

- `?action=files_list` - Get file list as JSON for AJAX refresh

---

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
