<?php
/**
 * View: Import page
 *
 * Displays the import progress and statistics.
 *
 * @var \BigDump\Core\View $view
 * @var \BigDump\Models\ImportSession $session Import session
 * @var array $statistics Statistics
 * @var bool $testMode Test mode
 * @var bool $ajaxEnabled AJAX enabled
 * @var int $delay Delay between sessions
 * @var array $nextParams Parameters for the next session
 * @var string|null $ajaxScript AJAX script (if applicable)
 * @var string|null $redirectScript Redirect script (if applicable)
 * @var array|null $autoTuner Auto-tuner performance data (if enabled)
 */
?>

<?php if (!$session->isFinished() && !$session->hasError()): ?>
<!-- Loading Overlay for SSE Connection -->
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" id="sseLoadingOverlay">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 text-center shadow-xl">
        <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">Connecting...</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">Establishing live connection</div>
    </div>
</div>
<?php endif; ?>

<div class="text-center mb-3">
    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Processing: <strong><?= $view->e($session->getFilename()) ?></strong></h3>
    <p class="text-gray-500 dark:text-gray-400">Starting from line: <?= number_format($session->getStartLine()) ?></p>
</div>

<?php if ($testMode): ?>
<div class="alert alert-warning">
    <strong>Test Mode</strong> - Queries are being parsed but NOT executed.
</div>
<?php endif; ?>

<?php if ($session->hasError()): ?>
<?php
    /**
     * Parse error message to extract display components
     *
     * Expected format from ImportService:
     * SQL Error at line {lineNum}:
     * Query: {displayQuery}
     * MySQL Error: {error}
     */
    $errorText = $session->getError() ?? '';
    $errorSummary = '';
    $errorLine = null;
    $mysqlError = '';
    $tableAlreadyExists = null; // Table name if "already exists" error

    if (!empty($errorText)) {
        $errorLines = explode("\n", $errorText);
        $mysqlErrorFound = false;
        $lineNumberFound = false;

        // Extract MySQL error for summary (look for "MySQL Error:" line)
        foreach ($errorLines as $line) {
            if (!$mysqlErrorFound && preg_match('/^MySQL Error:\s*(.+)$/i', $line, $matches)) {
                $mysqlError = trim($matches[1]);
                // Truncate if too long for summary
                $errorSummary = strlen($mysqlError) > 100
                    ? substr($mysqlError, 0, 100) . '...'
                    : $mysqlError;
                $mysqlErrorFound = true;

                // Check for "Table 'xxx' already exists" error (supports both 'table' and `table` formats)
                if (preg_match("/Table\s+['\"`]([^'\"`]+)['\"`]\s+already exists/i", $mysqlError, $tableMatch)) {
                    $tableAlreadyExists = $tableMatch[1];
                }
            }
            if (!$lineNumberFound && preg_match('/at line\s+(\d+)/i', $line, $matches)) {
                $errorLine = (int)$matches[1];
                $lineNumberFound = true;
            }
            // Early exit if both patterns found
            if ($mysqlErrorFound && $lineNumberFound) {
                break;
            }
        }

        // Fallback summary if no MySQL Error found
        if (empty($errorSummary) && isset($errorLines[0]) && $errorLines[0] !== '') {
            $errorSummary = strlen($errorLines[0]) > 100
                ? substr($errorLines[0], 0, 100) . '...'
                : $errorLines[0];
        }

        // Fallback: search for "already exists" in full error text if not found via MySQL Error line
        if ($tableAlreadyExists === null && preg_match("/Table\s+['\"`]([^'\"`]+)['\"`]\s+already exists/i", $errorText, $tableMatch)) {
            $tableAlreadyExists = $tableMatch[1];
        }
    } else {
        $errorSummary = 'Unknown error occurred';
    }
?>
<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6 mb-6" id="error-alert" tabindex="-1" role="alert" aria-live="assertive">
    <!-- Error Header with Icon and Summary -->
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
            <svg class="w-8 h-8 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.848c1.154 2-.29 4.5-2.899 4.5H4.645c-2.809 0-3.752-2.8-2.898-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-red-800 dark:text-red-200">Import Error</h2>
            <?php if ($errorSummary): ?>
            <div class="text-sm text-red-700 dark:text-red-300 mt-1">
                MySQL Error: <?= $view->e($errorSummary) ?>
            </div>
            <?php endif; ?>
            <?php if ($errorLine): ?>
            <div class="mt-2">
                <span class="inline-block bg-red-200 dark:bg-red-800 text-red-800 dark:text-red-200 px-2 py-0.5 rounded text-xs font-medium">Line <?= number_format($errorLine) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Collapsible Error Details -->
    <details class="mt-4" open>
        <summary class="cursor-pointer flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-300">
            <span>Show Full Error Details</span>
            <svg class="w-5 h-5 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
        </summary>
        <div class="mt-3">
            <pre class="bg-red-100 dark:bg-red-900/40 p-4 rounded-lg text-xs font-mono text-red-800 dark:text-red-200 overflow-x-auto whitespace-pre-wrap"><?= $view->e($errorText) ?></pre>
        </div>
    </details>
</div>
<?php endif; ?>

<?php if (!$statistics['gzip_mode'] && $statistics['pct_done'] !== null): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-6 shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="bg-gray-200 dark:bg-gray-700 rounded h-2.5 overflow-hidden">
        <div class="progress-bar h-full bg-gradient-to-r from-blue-500 to-blue-600 rounded transition-all duration-300 bg-[length:1rem_1rem] animate-progress-stripe" style="width: <?= $statistics['pct_done'] ?>%; background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);"></div>
    </div>
    <div class="flex justify-between items-center mt-2">
        <div class="elapsed-timer text-center flex-1" id="elapsedTimer">
            <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">Elapsed:</span>
            <span class="text-lg font-semibold text-gray-900 dark:text-gray-100 font-mono tracking-wider" id="elapsedTime">00:00:00</span>
        </div>
        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?= number_format($statistics['pct_done'], 2) ?>% Complete</div>
    </div>
</div>
<!-- ETA disabled - needs stabilization
<div class="eta-container text-center mb-3" id="eta-display">
    <span class="eta-icon">⏱️</span>
    <span class="eta-label">Estimated time remaining:</span>
    <span class="eta-value" id="eta-value">Calculating...</span>
</div>
-->
<?php elseif ($statistics['gzip_mode']): ?>
<div class="alert alert-info text-center">
    Progress bar not available for gzipped files
</div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-box">
        <div class="stat-value"><?= number_format($statistics['lines_done']) ?></div>
        <div class="stat-label">Lines Processed</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= number_format($statistics['queries_done']) ?></div>
        <div class="stat-label">Queries Executed</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= $statistics['mb_done'] ?></div>
        <div class="stat-label">MB Processed</div>
    </div>
    <?php if (!$statistics['gzip_mode'] && $statistics['pct_done'] !== null): ?>
    <div class="stat-box">
        <div class="stat-value"><?= number_format($statistics['pct_done'], 1) ?>%</div>
        <div class="stat-label">Complete</div>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-6 overflow-hidden">
    <div class="card-header">Detailed Statistics</div>
    <div class="p-0">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600"></th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600">This Session</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600">Total Done</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600">Remaining</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $calcClass = (!$statistics['finished'] && empty($statistics['estimates_frozen'])) ? ' animate-pulse' : ''; ?>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100">
                        <strong>Lines</strong>
                        <span class="inline-flex items-center justify-center w-4 h-4 ml-1 text-xs bg-gray-200 dark:bg-gray-600 rounded-full cursor-help text-gray-500 dark:text-gray-400 tooltip-trigger">?<span class="tooltip-content tooltip-multiline">Lines = SQL file lines read (including comments, CREATE, SET, etc.), not database records inserted. This count is normally higher than actual rows in database.</span></span>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['lines_this']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['lines_done']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100<?= $calcClass ?>"><?= $statistics['lines_togo'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['lines_togo']) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100<?= $calcClass ?>"><?= $statistics['lines_total'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['lines_total']) : '?' ?></td>
                </tr>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong>Queries</strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['queries_this']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['queries_done']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100<?= $calcClass ?>"><?= $statistics['queries_togo'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['queries_togo']) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100<?= $calcClass ?>"><?= $statistics['queries_total'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['queries_total']) : '?' ?></td>
                </tr>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong>Bytes</strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['bytes_this']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= number_format($statistics['bytes_done']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['bytes_togo'] !== null ? number_format($statistics['bytes_togo']) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['bytes_total'] !== null ? number_format($statistics['bytes_total']) : '?' ?></td>
                </tr>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong>KB</strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['kb_this'] ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['kb_done'] ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['kb_togo'] ?? '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['kb_total'] ?? '?' ?></td>
                </tr>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong>MB</strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['mb_this'] ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['mb_done'] ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['mb_togo'] ?? '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $statistics['mb_total'] ?? '?' ?></td>
                </tr>
                <?php if (!$statistics['gzip_mode']): ?>
                <tr class="even:bg-gray-50 dark:even:bg-gray-800/50">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong>%</strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= isset($statistics['pct_this']) ? number_format($statistics['pct_this'], 2) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= isset($statistics['pct_done']) ? number_format($statistics['pct_done'], 2) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= isset($statistics['pct_togo']) ? number_format($statistics['pct_togo'], 2) : '?' ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100">100.00</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Performance Section (Auto-Tuner) -->
<?php if (isset($autoTuner) && $autoTuner['enabled']): ?>
<div class="card card-body mb-6">
    <h3 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4 font-semibold">Performance (Auto-Tuner)</h3>
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="metric-box">
            <span class="metric-label">System</span>
            <span class="metric-value" id="perf-system"><?= htmlspecialchars($autoTuner['os']) ?></span>
        </div>
        <div class="metric-box">
            <span class="metric-label">RAM Available</span>
            <span class="metric-value" id="perf-ram"><?= htmlspecialchars($autoTuner['available_ram_formatted']) ?></span>
        </div>
        <div class="metric-box">
            <span class="metric-label">File Category</span>
            <span class="metric-value" id="perf-category">
                <?php
                // Color-coded badge for file category
                $categoryColors = [
                    'tiny' => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
                    'small' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
                    'medium' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                    'large' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
                    'massive' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
                ];
                $category = $autoTuner['file_category'] ?? 'small';
                $categoryLabel = $autoTuner['file_category_label'] ?? ucfirst($category);
                $colorClass = $categoryColors[$category] ?? $categoryColors['small'];
                ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium <?= $colorClass ?>">
                    <?= htmlspecialchars($categoryLabel) ?>
                    <?php if (!empty($autoTuner['is_bulk_insert'])): ?>
                    <span class="text-green-600 dark:text-green-400 font-bold" title="Bulk INSERT detected">+B</span>
                    <?php endif; ?>
                </span>
            </span>
        </div>
        <div class="metric-box">
            <span class="metric-label">Batch Size</span>
            <span class="metric-value" id="perf-batch"><?= number_format($autoTuner['batch_size']) ?> (auto)</span>
        </div>
        <div class="metric-box">
            <span class="metric-label">Memory</span>
            <span class="metric-value" id="perf-memory">
                <?= $autoTuner['memory_percentage'] ?>% / <?= number_format(($autoTuner['target_ram_usage'] ?? 0.6) * 100) ?>% target
            </span>
        </div>
        <div class="metric-box">
            <span class="metric-label">Realtime Speed</span>
            <span class="metric-value" id="perf-speed">
                <?= number_format($autoTuner['speed_lps'], 0) ?> l/s
                <?php
                // Speed trend arrow
                $trend = $autoTuner['speed_trend'] ?? 'calculating';
                $trendIcons = [
                    'increasing' => ['icon' => '↑', 'color' => 'text-green-600 dark:text-green-400'],
                    'decreasing' => ['icon' => '↓', 'color' => 'text-red-600 dark:text-red-400'],
                    'stable' => ['icon' => '→', 'color' => 'text-gray-500 dark:text-gray-400'],
                ];
                if (isset($trendIcons[$trend])):
                ?>
                <span class="ml-1 <?= $trendIcons[$trend]['color'] ?>" title="Speed trend: <?= htmlspecialchars($trend) ?>">
                    <?= $trendIcons[$trend]['icon'] ?>
                </span>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php if (!empty($autoTuner['adjustment'])): ?>
    <div class="mt-3 text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 rounded" id="adjustment-notice">
        <?= htmlspecialchars($autoTuner['adjustment']) ?>
    </div>
    <?php else: ?>
    <div class="mt-3 text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 rounded" id="adjustment-notice" style="display:none;"></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($session->isFinished() && !$session->hasError()): ?>
<div class="alert alert-success text-center">
    <strong style="font-size: 18px;">Import Completed Successfully!</strong><br><br>
    Total queries executed: <strong><?= number_format($statistics['queries_done']) ?></strong><br>
    Total lines processed: <strong><?= number_format($statistics['lines_done']) ?></strong><br><br>
    <span style="color: #c53030; font-weight: bold;">
        IMPORTANT: Delete your dump file and this script from the server for security!
    </span>
</div>

<div class="text-center mt-3">
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-blue">Back to File List</a>
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-cyan" style="margin-left: 10px;">Back to Home</a>
</div>

<?php elseif (!$session->hasError()): ?>

    <?php if ($delay > 0): ?>
    <p class="text-center text-gray-500 dark:text-gray-400">
        Waiting <?= $delay ?>ms before next session...
    </p>
    <?php endif; ?>

    <noscript>
        <div class="alert alert-warning text-center">
            JavaScript is disabled. Click the link below to continue manually.<br><br>
            <a href="<?= $view->e($scriptUri) ?>?action=start_import" class="btn btn-blue">
                Continue Import
            </a>
        </div>
    </noscript>

    <div class="text-center mt-3">
        <a href="<?= $view->e($scriptUri) ?>?action=stop_import" class="btn btn-gray" onclick="return confirm('Are you sure you want to stop the import? Progress will be lost.');">
            STOP Import
        </a>
        <span class="text-muted" style="margin-left: 15px;">or wait for automatic continuation</span>
    </div>

    <?php if (isset($ajaxScript)): ?>
        <?= $ajaxScript ?>
    <?php elseif (isset($redirectScript)): ?>
        <?= $redirectScript ?>
    <?php endif; ?>

<?php else: ?>

<div style="display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 30px; margin-bottom: 25px;">
    <?php if ($tableAlreadyExists): ?>
    <a href="<?= $view->e($scriptUri) ?>?action=drop_restart&table=<?= urlencode($tableAlreadyExists) ?>&fn=<?= urlencode($session->getFilename()) ?>"
       class="btn btn-amber"
       onclick="return confirm('This will DROP TABLE `<?= $view->e($tableAlreadyExists) ?>` and restart the import. Continue?');">
        Drop "<?= $view->e($tableAlreadyExists) ?>" &amp; Restart Import
    </a>
    <span class="text-muted">or</span>
    <?php endif; ?>
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-blue">Start Over (resume)</a>
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-cyan">Back to Home</a>
    <?php if (!$tableAlreadyExists): ?>
    <span class="text-muted">(DROP old tables before restarting)</span>
    <?php endif; ?>
</div>

<?php endif; ?>
