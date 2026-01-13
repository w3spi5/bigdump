<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\ImportSession;

/**
 * AjaxService Class - Service for SSE JavaScript generation.
 *
 * This service generates JavaScript code for the SSE-based
 * real-time import progress display.
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class AjaxService
{
    /**
     * Configuration.
     * @var Config
     */
    private Config $config;

    /**
     * Constructor.
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Generates the SSE JavaScript script for real-time import updates.
     *
     * Uses Server-Sent Events (EventSource) for real-time updates.
     * Session state is managed server-side via PHP sessions.
     *
     * @param ImportSession $session Import session
     * @param string $scriptUri Script URI
     * @return string JavaScript code
     */
    public function createAjaxScript(ImportSession $session, string $scriptUri): string
    {
        $safeScriptUri = $this->escapeJsString($scriptUri);
        $safeFilename = $this->escapeJsString($session->getFilename());

        $js = <<<JAVASCRIPT
<script type="text/javascript">
(function() {
    'use strict';

    var scriptUri = '{$safeScriptUri}';
    var filename = '{$safeFilename}';

    /**
     * Displays an import error directly in the page (no popup).
     * Creates a styled error container similar to the PHP-rendered version.
     *
     * @param {string} message Error message
     * @param {object} stats Statistics object (may contain hasCreateTable)
     */
    function displayErrorInPage(message, stats) {
        var hasCreateTable = stats && stats.hasCreateTable ? stats.hasCreateTable : false;

        // Check for "Table already exists" error
        var alreadyExistsMatch = message.match(/Table\s+['"`]([^'"`]+)['"`]\s+already exists/i);

        // Check for "Table doesn't exist" error
        var doesntExistMatch = message.match(/Table\s+['"`]([^'"`]+)['"`]\s+doesn't exist/i);

        var actionButtons = '';
        var helpText = '';

        if (alreadyExistsMatch) {
            var tableName = alreadyExistsMatch[1];
            if (hasCreateTable) {
                // Table exists AND CREATE TABLE in file → show Drop & Restart button
                actionButtons = '<a href="' + scriptUri + '/import/drop-restart?table=' + encodeURIComponent(tableName) + '&fn=' + encodeURIComponent(filename) + '" ' +
                    'class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-amber-500 hover:bg-amber-600 text-white" ' +
                    'onclick="return confirm(\'This will DROP TABLE `' + escapeHtml(tableName) + '` and restart the import. Continue?\');">' +
                    'Drop "' + escapeHtml(tableName) + '" &amp; Restart Import</a>' +
                    '<span class="text-gray-500 dark:text-gray-400 mx-2">or</span>';
            } else {
                // Table exists but NO CREATE TABLE in file → show warning message
                helpText = '<div class="text-amber-700 dark:text-amber-400 text-sm mb-3">' +
                    '<strong>Note:</strong> Table "' + escapeHtml(tableName) + '" already exists but no CREATE TABLE found in file. ' +
                    'Drop it manually or use a different file.</div>';
            }
        } else if (doesntExistMatch) {
            var tableName = doesntExistMatch[1];
            if (hasCreateTable) {
                // Table doesn't exist BUT CREATE TABLE in file → show Restart from Beginning button
                actionButtons = '<a href="' + scriptUri + '/import/restart?fn=' + encodeURIComponent(filename) + '" ' +
                    'class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-green-500 hover:bg-green-600 text-white">' +
                    'Restart from Beginning</a>' +
                    '<span class="text-gray-500 dark:text-gray-400 mx-2">or</span>';
            } else {
                // Table missing and NO CREATE TABLE in file → show warning message
                helpText = '<div class="text-red-700 dark:text-red-400 text-sm mb-3">' +
                    '<strong>Error:</strong> Table "' + escapeHtml(tableName) + '" is missing. No CREATE TABLE found in file. ' +
                    'Create the table manually before importing.</div>';
            }
        }

        // Create error HTML with Tailwind classes (matching import.php error display)
        var errorHtml = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6 mb-6" id="sse-error-alert" role="alert">' +
            '<div class="flex items-start gap-4">' +
                '<div class="flex-shrink-0">' +
                    '<svg class="w-8 h-8 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">' +
                        '<path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.848c1.154 2-.29 4.5-2.899 4.5H4.645c-2.809 0-3.752-2.8-2.898-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>' +
                    '</svg>' +
                '</div>' +
                '<div class="flex-1">' +
                    '<h2 class="text-lg font-semibold text-red-800 dark:text-red-200">Import Error</h2>' +
                    '<div class="text-sm text-red-700 dark:text-red-300 mt-1">' + escapeHtml(message.split('\\n')[0]) + '</div>' +
                '</div>' +
            '</div>' +
            '<details class="mt-4" open>' +
                '<summary class="cursor-pointer flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-300">' +
                    '<span>Show Full Error Details</span>' +
                    '<svg class="w-5 h-5 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">' +
                        '<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>' +
                    '</svg>' +
                '</summary>' +
                '<div class="mt-3">' +
                    '<pre class="bg-red-100 dark:bg-red-900/40 p-4 rounded-lg text-xs font-mono text-red-800 dark:text-red-200 overflow-x-auto whitespace-pre-wrap">' + escapeHtml(message) + '</pre>' +
                '</div>' +
            '</details>' +
        '</div>' +
        helpText +
        '<div style="display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 30px; margin-bottom: 25px;">' +
            actionButtons +
            '<a href="' + scriptUri + '" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-blue-600 hover:bg-blue-700 text-white">Resume</a>' +
            '<a href="/" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-cyan-500 hover:bg-cyan-600 text-white">Back to Home</a>' +
        '</div>';

        // Find main content area and insert error at the beginning
        var mainContent = document.querySelector('main');
        if (mainContent) {
            // Remove existing SSE error if any
            var existingError = document.getElementById('sse-error-alert');
            if (existingError) existingError.parentElement.remove();
            mainContent.insertAdjacentHTML('afterbegin', errorHtml);
        }

        // Hide progress elements
        var progressElements = document.querySelectorAll('.grid, table, noscript + div, .text-center.mt-3');
        progressElements.forEach(function(el) {
            if (!el.closest('#sse-error-alert')) {
                el.style.display = 'none';
            }
        });
    }

    /**
     * Escapes HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ETA calculation variables
    var startTime = Date.now();
    var lastPctDone = 0;
    var etaSeconds = null;
    var etaHistory = [];

    // Elapsed timer variables
    var elapsedTimerInterval = null;

    /**
     * Formats elapsed seconds into HH:MM:SS format.
     */
    function formatElapsedTime(seconds) {
        var hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
        var minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
        var secs = String(seconds % 60).padStart(2, '0');
        return hours + ':' + minutes + ':' + secs;
    }

    /**
     * Updates the elapsed timer display.
     */
    function updateElapsedTimer() {
        var elapsed = Math.floor((Date.now() - startTime) / 1000);
        var el = document.getElementById('elapsedTime');
        if (el) {
            el.textContent = formatElapsedTime(elapsed);
        }
    }

    /**
     * Starts the elapsed timer (call once when import begins).
     */
    function startElapsedTimer() {
        // Reset start time
        startTime = Date.now();
        // Clear any existing interval
        if (elapsedTimerInterval) {
            clearInterval(elapsedTimerInterval);
        }
        // Update immediately, then every second
        updateElapsedTimer();
        elapsedTimerInterval = setInterval(updateElapsedTimer, 1000);
    }

    /**
     * Stops the elapsed timer.
     */
    function stopElapsedTimer() {
        if (elapsedTimerInterval) {
            clearInterval(elapsedTimerInterval);
            elapsedTimerInterval = null;
        }
    }

    /**
     * Formats seconds into human-readable time string.
     */
    function formatEta(seconds) {
        if (seconds === null || seconds < 0 || !isFinite(seconds)) {
            return 'Calculating...';
        }
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        }
        if (seconds < 3600) {
            var mins = Math.floor(seconds / 60);
            var secs = Math.round(seconds % 60);
            return mins + 'm ' + secs + 's';
        }
        var hours = Math.floor(seconds / 3600);
        var mins = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + mins + 'm';
    }

    /**
     * Updates the ETA display based on progress.
     */
    function updateEta(pctDone) {
        var etaEl = document.getElementById('eta-value');
        if (!etaEl) return;

        pctDone = parseFloat(pctDone) || 0;
        if (pctDone <= 0) {
            etaEl.textContent = 'Calculating...';
            return;
        }

        var elapsed = (Date.now() - startTime) / 1000;
        var pctRemaining = 100 - pctDone;

        // Calculate instantaneous ETA
        var rate = pctDone / elapsed; // % per second
        if (rate > 0) {
            var instantEta = pctRemaining / rate;

            // Smooth ETA using moving average
            etaHistory.push(instantEta);
            if (etaHistory.length > 5) {
                etaHistory.shift();
            }
            etaSeconds = etaHistory.reduce(function(a, b) { return a + b; }, 0) / etaHistory.length;
        }

        etaEl.textContent = formatEta(etaSeconds);
        lastPctDone = pctDone;
    }

    // Update ETA every second
    setInterval(function() {
        if (etaSeconds !== null && etaSeconds > 0) {
            etaSeconds = Math.max(0, etaSeconds - 1);
            var etaEl = document.getElementById('eta-value');
            if (etaEl) etaEl.textContent = formatEta(etaSeconds);
        }
    }, 1000);

    /**
     * Updates a table cell's text content.
     */
    function updateCell(cell, value) {
        if (!cell) return;
        if (cell.firstChild && cell.firstChild.nodeType === 3) {
            cell.firstChild.nodeValue = value;
        } else {
            cell.textContent = value;
        }
    }

    /**
     * Formats a value, handling null/undefined with '?'
     */
    function formatValue(value) {
        if (value === null || value === undefined) return '?';
        return String(value);
    }

    /**
     * Updates all UI elements from SSE progress data.
     * Same DOM selectors as the original AJAX version.
     */
    function updateProgress(data) {
        var stats = data.stats;
        var session = data.session;

        // Update line number display (using textContent for XSS safety)
        if (session && session.start !== undefined) {
            var paragraphs = document.getElementsByTagName('p');
            if (paragraphs[1]) {
                paragraphs[1].textContent = 'Starting from line: ' + Number(session.start).toLocaleString();
            }
        }

        // Update statistics table
        // Table structure: 6 rows x 5 cols (label + 4 values per row)
        // Each row: [label, this_session, total_done, remaining, total]
        // Row 1: Lines, Row 2: Queries, Row 3: Bytes, Row 4: KB, Row 5: MB, Row 6: %
        var table = document.querySelector('table tbody');
        if (table && stats) {
            var rows = table.getElementsByTagName('tr');
            var tableData = [
                [stats.lines_this, stats.lines_done, stats.lines_togo, stats.lines_total],
                [stats.queries_this, stats.queries_done, stats.queries_togo, stats.queries_total],
                [stats.bytes_this, stats.bytes_done, stats.bytes_togo, stats.bytes_total],
                [stats.kb_this, stats.kb_done, stats.kb_togo, stats.kb_total],
                [stats.mb_this, stats.mb_done, stats.mb_togo, stats.mb_total],
                [stats.pct_this, stats.pct_done, stats.pct_togo, stats.pct_total]
            ];

            for (var row = 0; row < rows.length && row < tableData.length; row++) {
                var cells = rows[row].getElementsByTagName('td');
                var rowData = tableData[row];
                // Skip first cell (label), update cells 1-4 (values)
                for (var col = 1; col < cells.length && col <= rowData.length; col++) {
                    var value = rowData[col - 1];
                    // Format numbers with locale separators, handle null as '?'
                    if (value === null || value === undefined) {
                        updateCell(cells[col], '?');
                    } else if (row === 5) {
                        // Percentage row - format with 2 decimals
                        updateCell(cells[col], parseFloat(value).toFixed(2));
                    } else if (typeof value === 'number') {
                        updateCell(cells[col], Number(value).toLocaleString());
                    } else {
                        updateCell(cells[col], String(value));
                    }
                }
            }
        }

        // Update progress bar (SAME SELECTOR: .progress-bar)
        var progressBar = document.querySelector('.progress-bar');
        var pctDone = stats ? stats.pct_done : null;
        if (progressBar && pctDone !== null && pctDone !== undefined) {
            progressBar.style.width = pctDone + '%';
        }

        // Update ETA based on new progress
        updateEta(pctDone);

        // Update stat boxes (SAME SELECTORS: .stat-box .stat-value)
        var statBoxes = document.querySelectorAll('.stat-box .stat-value');
        if (statBoxes.length >= 3 && stats) {
            statBoxes[0].textContent = Number(stats.lines_done || 0).toLocaleString();
            statBoxes[1].textContent = Number(stats.queries_done || 0).toLocaleString();
            statBoxes[2].textContent = stats.mb_done || '0';
            if (statBoxes[3] && pctDone !== null) {
                statBoxes[3].textContent = parseFloat(pctDone).toFixed(1) + '%';
            }
        }

        // Update percentage next to elapsed timer
        if (pctDone !== null && pctDone !== undefined) {
            var pctDisplay = document.querySelector('#elapsedTimer + div');
            if (pctDisplay) {
                pctDisplay.textContent = parseFloat(pctDone).toFixed(2) + '% Complete';
            }
        }

        // AutoTuner updates (SAME IDs: #perf-batch, #perf-memory, #perf-speed, #adjustment-notice)
        if (stats) {
            var batchSize = stats.batch_size;
            var memoryPct = stats.memory_percentage;
            var speedLps = stats.speed_lps;
            var adjustment = stats.auto_tune_adjustment;

            if (batchSize) {
                var batchEl = document.getElementById('perf-batch');
                if (batchEl) batchEl.textContent = parseInt(batchSize).toLocaleString() + ' (auto)';
            }
            if (memoryPct !== undefined && memoryPct !== null) {
                var memEl = document.getElementById('perf-memory');
                if (memEl) memEl.textContent = memoryPct + '%';
            }
            if (speedLps !== undefined && speedLps !== null) {
                var speedEl = document.getElementById('perf-speed');
                if (speedEl) speedEl.textContent = Number(speedLps).toLocaleString() + ' l/s';
            }
            if (adjustment && adjustment !== '') {
                var adjEl = document.getElementById('adjustment-notice');
                if (adjEl) {
                    adjEl.textContent = adjustment;
                    adjEl.style.display = 'block';
                }
            }
        }

        // Update calculating indicator for estimated values (Lines/Queries: Remaining & Total columns)
        // estimates_frozen is true once we've processed 5% of file (stable estimate)
        var estimatesFrozen = stats && stats.estimates_frozen;
        var table = document.querySelector('table tbody');
        if (table) {
            var rows = table.getElementsByTagName('tr');
            // Row 0 = Lines, Row 1 = Queries
            // Columns: 0=label, 1=this_session, 2=total_done, 3=remaining, 4=total
            for (var r = 0; r < 2 && r < rows.length; r++) {
                var cells = rows[r].getElementsByTagName('td');
                // Apply/remove calculating class on Remaining (col 3) and Total (col 4)
                if (cells[3]) {
                    if (estimatesFrozen) {
                        cells[3].classList.remove('calculating');
                    } else {
                        cells[3].classList.add('calculating');
                    }
                }
                if (cells[4]) {
                    if (estimatesFrozen) {
                        cells[4].classList.remove('calculating');
                    } else {
                        cells[4].classList.add('calculating');
                    }
                }
            }
        }
    }

    // Connection state tracking
    var source = null;
    var connected = false;
    var reconnectAttempts = 0;
    var maxReconnectAttempts = 3;
    var intentionalClose = false;

    // ========== SMOOTH TRANSITION ENGINE ==========
    // Instead of predicting future values (which causes "jumps back"),
    // this engine smoothly animates FROM the previous server value TO the new one.
    // Values only ever increase, never decrease.
    var smoothing = {
        enabled: true,
        interval: null,
        fps: 60,

        // Current displayed values (what user sees)
        displayLines: 0,
        displayBytes: 0,
        displayQueries: 0,

        // Target values (from last server update)
        targetLines: 0,
        targetBytes: 0,
        targetQueries: 0,

        // For percentage calculation
        bytesTotal: 0,

        start: function() {
            if (this.interval) return;
            var self = this;
            this.interval = setInterval(function() {
                self.tick();
            }, 1000 / this.fps);
        },

        stop: function() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        // Called when real stats arrive from server
        sync: function(stats) {
            // Set new targets (always >= current to prevent going backwards)
            this.targetLines = Math.max(this.displayLines, stats.lines_done || 0);
            this.targetBytes = Math.max(this.displayBytes, stats.bytes_done || 0);
            this.targetQueries = Math.max(this.displayQueries, stats.queries_done || 0);
            this.bytesTotal = stats.bytes_total || 0;
        },

        // Called every frame - smoothly move display values toward targets
        tick: function() {
            if (!this.enabled) return;

            // Easing factor: how fast to catch up (lower = smoother/slower)
            // 0.05 = very smooth, 0.15 = normal, 0.5 = fast
            var easing = 0.15;

            // Smoothly move toward targets
            this.displayLines += (this.targetLines - this.displayLines) * easing;
            this.displayBytes += (this.targetBytes - this.displayBytes) * easing;
            this.displayQueries += (this.targetQueries - this.displayQueries) * easing;

            // Snap to target when very close (avoid endless tiny increments)
            if (Math.abs(this.targetLines - this.displayLines) < 1) {
                this.displayLines = this.targetLines;
            }
            if (Math.abs(this.targetBytes - this.displayBytes) < 1) {
                this.displayBytes = this.targetBytes;
            }
            if (Math.abs(this.targetQueries - this.displayQueries) < 0.1) {
                this.displayQueries = this.targetQueries;
            }

            this.updateDisplay();
        },

        updateDisplay: function() {
            var lines = Math.floor(this.displayLines);
            var bytes = Math.floor(this.displayBytes);
            var queries = Math.floor(this.displayQueries);

            // Update stat boxes only (table is updated by updateProgress on server events)
            var statBoxes = document.querySelectorAll('.stat-box .stat-value');
            if (statBoxes.length >= 3) {
                statBoxes[0].textContent = Number(lines).toLocaleString();
                statBoxes[1].textContent = Number(queries).toLocaleString();
                var mbDone = (bytes / 1048576).toFixed(2);
                statBoxes[2].textContent = mbDone;
            }

            // Update progress bar and percentage display
            if (this.bytesTotal > 0) {
                var pct = (bytes / this.bytesTotal) * 100;
                var progressBar = document.querySelector('.progress-bar');
                if (progressBar) {
                    progressBar.style.width = pct + '%';
                }
                // Update percentage in stat box
                if (statBoxes[3]) {
                    statBoxes[3].textContent = pct.toFixed(1) + '%';
                }
                // Update percentage next to elapsed timer
                var pctDisplay = document.querySelector('#elapsedTimer + div');
                if (pctDisplay) {
                    pctDisplay.textContent = pct.toFixed(2) + '% Complete';
                }
            }
            // Note: Table is NOT updated here - only on real server events via updateProgress()
        }
    };

    // SSE URL - use clean URL path
    var sseUrl = scriptUri + '/import/sse';

    // Create and setup EventSource connection
    function createConnection() {
        console.log('SSE: Connecting to', sseUrl);

        source = new EventSource(sseUrl);

        // Handle initial connection established event
        source.addEventListener('connected', function(e) {
            console.log('SSE: Connection established');
            console.log('SSE: Connected event data:', e.data);
            console.log('SSE: Event type:', e.type);
            console.log('SSE: Full event object:', e);
            connected = true;
            reconnectAttempts = 0;
            // Hide loading overlay
            var overlay = document.getElementById('sseLoadingOverlay');
            if (overlay) {
                console.log('SSE: Loading overlay found, hiding it');
                console.log('SSE: Overlay current display:', overlay.style.display);
                overlay.style.display = 'none';
                console.log('SSE: Overlay new display:', overlay.style.display);
            } else {
                console.warn('SSE: Loading overlay not found in DOM');
                console.log('SSE: Searching for overlay by class...');
                var overlayByClass = document.querySelector('.sse-loading-overlay');
                if (overlayByClass) {
                    console.log('SSE: Found overlay by class, hiding it');
                    overlayByClass.style.display = 'none';
                }
            }
            // Start elapsed timer
            startElapsedTimer();
        });

        // Handle progress events (real-time updates)
        source.addEventListener('progress', function(e) {
            try {
                var data = JSON.parse(e.data);
                connected = true;
                // Hide loading overlay on first progress (fallback if 'connected' event missed)
                var overlay = document.getElementById('sseLoadingOverlay');
                if (overlay && overlay.style.display !== 'none') {
                    console.log('SSE: Hiding loading overlay (fallback from progress event)');
                    overlay.style.display = 'none';
                }
                // Additional fallback: try by class name
                if (!overlay || overlay.style.display !== 'none') {
                    var overlayByClass = document.querySelector('.sse-loading-overlay');
                    if (overlayByClass && overlayByClass.style.display !== 'none') {
                        console.log('SSE: Hiding overlay by class (fallback)');
                        overlayByClass.style.display = 'none';
                    }
                }
                // Sync smoothing engine with real server data
                if (data.stats) {
                    smoothing.sync(data.stats);
                }
                updateProgress(data);
            } catch (err) {
                console.error('SSE: Failed to parse progress data:', err);
            }
        });

        // Handle completion event
        source.addEventListener('complete', function(e) {
            console.log('SSE: Import complete');
            smoothing.stop();
            stopElapsedTimer();
            intentionalClose = true;
            source.close();
            location.reload();
        });

        // Handle error events from server (import errors)
        source.addEventListener('error', function(e) {
            if (e.data) {
                try {
                    var data = JSON.parse(e.data);
                    console.log('SSE: Server error event:', data);

                    // Check for timing issue - session not ready yet
                    // This can happen if SSE connects before session is fully written
                    if (data.message && data.message.indexOf('No active import session') !== -1) {
                        console.log('SSE: Session not ready, will retry...');
                        reconnectAttempts++;
                        if (reconnectAttempts < maxReconnectAttempts) {
                            source.close();
                            // Exponential backoff: 100ms, 200ms, 400ms, 800ms, 1600ms
                            var delay = Math.min(100 * Math.pow(2, reconnectAttempts - 1), 2000);
                            console.log('SSE: Retrying in ' + delay + 'ms (attempt ' + reconnectAttempts + '/' + maxReconnectAttempts + ')');
                            setTimeout(function() {
                                createConnection();
                            }, delay);
                            return; // Don't display error yet
                        }
                        // Max retries exceeded - show error
                        console.log('SSE: Max retries exceeded, showing error');
                    }

                    smoothing.stop();
                    stopElapsedTimer();
                    // Display error in page with hasCreateTable info
                    // Combine stats and hasCreateTable into stats object for displayErrorInPage
                    var errorStats = data.stats || {};
                    errorStats.hasCreateTable = data.hasCreateTable || false;
                    displayErrorInPage(data.message || 'Unknown error', errorStats);
                } catch (err) {
                    smoothing.stop();
                    stopElapsedTimer();
                    displayErrorInPage('Import error occurred', null);
                }
                intentionalClose = true;
                source.close();
                // Don't reload - show error in current page
            }
        });

        // Handle connection errors (network/server issues)
        // EventSource auto-reconnects, server manages session state
        source.onerror = function(e) {
            if (intentionalClose) {
                return;
            }

            console.log('SSE: Connection error, EventSource will auto-reconnect');
            console.log('SSE: readyState:', source.readyState);

            // Only show alert after multiple failures
            if (source.readyState === EventSource.CLOSED) {
                reconnectAttempts++;
                if (reconnectAttempts >= maxReconnectAttempts) {
                    stopElapsedTimer();
                    alert('Connection lost after ' + maxReconnectAttempts + ' attempts. Please refresh the page.');
                    source.close();
                }
            }
        };
    }

    // Wait for DOM before starting
    document.addEventListener('DOMContentLoaded', function() {
        smoothing.start();
        createConnection();
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        intentionalClose = true;
        if (source) source.close();
    });

    // Handle Stop Import button - close SSE before navigation
    document.addEventListener('click', function(e) {
        var stopLink = e.target.closest('a[href*="stop_import"]');
        if (stopLink) {
            // Close SSE connection immediately
            intentionalClose = true;
            stopElapsedTimer();
            smoothing.stop();
            if (source) {
                source.close();
                source = null;
                console.log('SSE: Closed by user (Stop Import)');
            }
            // Small delay to ensure connection is closed before navigation
            e.preventDefault();
            setTimeout(function() {
                window.location.href = stopLink.href;
            }, 100);
        }
    });
})();
</script>
JAVASCRIPT;

        return $js;
    }

    /**
     * Generates the automatic redirect script (non-AJAX mode).
     *
     * @param ImportSession $session Import session
     * @param string $scriptUri Script URI
     * @return string JavaScript code
     */
    public function createRedirectScript(ImportSession $session, string $scriptUri): string
    {
        $params = $session->getNextSessionParams();
        $delay = $this->config->get('delaypersession', 0);

        $url = $scriptUri . '?' . http_build_query($params);
        $safeUrl = $this->escapeJsString($url);

        return <<<JAVASCRIPT
<script type="text/javascript">
    window.setTimeout(function() {
        location.href = '{$safeUrl}';
    }, 500 + {$delay});
</script>
JAVASCRIPT;
    }

    /**
     * Checks if AJAX mode is enabled.
     *
     * @return bool True if AJAX enabled
     */
    public function isAjaxEnabled(): bool
    {
        return $this->config->get('ajax', true);
    }

    /**
     * Retrieves the delay between sessions.
     *
     * @return int Delay in milliseconds
     */
    public function getDelay(): int
    {
        return $this->config->get('delaypersession', 0);
    }

    /**
     * Escapes a string for safe use in JavaScript.
     *
     * Prevents XSS injections by escaping special characters,
     * including sequences that could terminate a script block
     * and Unicode line terminators (U+2028, U+2029).
     *
     * @param string $string String to escape
     * @return string JavaScript-safe string
     */
    private function escapeJsString(string $string): string
    {
        // Escape control characters, quotes and backslashes
        $escaped = addcslashes($string, "\0..\37\"'\\");

        // Escape Unicode line terminators (U+2028, U+2029)
        // These are valid in JSON but treated as line terminators in JavaScript
        $escaped = str_replace(["\u{2028}", "\u{2029}"], ['\\u2028', '\\u2029'], $escaped);

        // Escape sequences that could break out of script context
        // </script> could terminate the script block prematurely
        $escaped = str_replace(['</', '<!--', '-->'], ['<\\/', '<\\!--', '--\\>'], $escaped);

        return $escaped;
    }
}
