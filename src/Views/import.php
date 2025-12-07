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

<style>
/* Performance Section Styles */
.performance-section {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.performance-section h3 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}
.perf-box {
    background: white;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.perf-label {
    display: block;
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 5px;
}
.perf-value {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #212529;
}
.adjustment-notice {
    margin-top: 10px;
    padding: 8px 12px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    color: #155724;
    font-size: 13px;
}
</style>

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
    } else {
        $errorSummary = 'Unknown error occurred';
    }
?>
<div class="error-container" id="error-alert" tabindex="-1" role="alert" aria-live="assertive">
    <!-- Error Header with Icon and Summary -->
    <div class="error-header">
        <div class="error-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.648c1.154 2-.29 4.5-2.699 4.5H4.645c-2.609 0-3.752-2.6-2.698-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="error-header__content">
            <h2 class="error-header__title">Import Error</h2>
            <?php if ($errorSummary): ?>
            <div class="error-header__summary">
                MySQL Error: <?= $view->e($errorSummary) ?>
            </div>
            <?php endif; ?>
            <?php if ($errorLine): ?>
            <div class="error-header__line">
                <span class="error-line-badge">Line <?= number_format($errorLine) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Collapsible Error Details -->
    <details class="error-details" open>
        <summary class="error-details__toggle">
            <span class="error-details__toggle-text">Show Full Error Details</span>
            <svg class="error-details__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
        </summary>
        <div class="error-details__content">
            <pre class="error-details__pre"><?= $view->e($errorText) ?></pre>
        </div>
    </details>
</div>
<?php endif; ?>

<div class="text-center mb-3">
    <h3>Processing: <strong><?= $view->e($session->getFilename()) ?></strong></h3>
    <p class="text-muted">Starting from line: <?= number_format($session->getStartLine()) ?></p>
</div>

<?php if (!$statistics['gzip_mode'] && $statistics['pct_done'] !== null): ?>
<div class="progress-container mb-3">
    <div class="progress-bar" style="width: <?= $statistics['pct_done'] ?>%">
        <?= number_format($statistics['pct_done'], 2) ?>%
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

<div class="stats-grid">
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
        <div class="stat-value"><?= number_format($statistics['pct_done'], 2) ?>%</div>
        <div class="stat-label">Complete</div>
    </div>
    <?php endif; ?>
</div>

<table>
    <thead>
        <tr>
            <th></th>
            <th>This Session</th>
            <th>Total Done</th>
            <th>Remaining</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Lines</strong></td>
            <td><?= number_format($statistics['lines_this']) ?></td>
            <td><?= number_format($statistics['lines_done']) ?></td>
            <td><?= $statistics['lines_togo'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['lines_togo']) : '?' ?></td>
            <td><?= $statistics['lines_total'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['lines_total']) : '?' ?></td>
        </tr>
        <tr>
            <td><strong>Queries</strong></td>
            <td><?= number_format($statistics['queries_this']) ?></td>
            <td><?= number_format($statistics['queries_done']) ?></td>
            <td><?= $statistics['queries_togo'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['queries_togo']) : '?' ?></td>
            <td><?= $statistics['queries_total'] !== null ? ($statistics['finished'] ? '' : '~') . number_format($statistics['queries_total']) : '?' ?></td>
        </tr>
        <tr>
            <td><strong>Bytes</strong></td>
            <td><?= number_format($statistics['bytes_this']) ?></td>
            <td><?= number_format($statistics['bytes_done']) ?></td>
            <td><?= $statistics['bytes_togo'] !== null ? number_format($statistics['bytes_togo']) : '?' ?></td>
            <td><?= $statistics['bytes_total'] !== null ? number_format($statistics['bytes_total']) : '?' ?></td>
        </tr>
        <tr>
            <td><strong>KB</strong></td>
            <td><?= $statistics['kb_this'] ?></td>
            <td><?= $statistics['kb_done'] ?></td>
            <td><?= $statistics['kb_togo'] ?? '?' ?></td>
            <td><?= $statistics['kb_total'] ?? '?' ?></td>
        </tr>
        <tr>
            <td><strong>MB</strong></td>
            <td><?= $statistics['mb_this'] ?></td>
            <td><?= $statistics['mb_done'] ?></td>
            <td><?= $statistics['mb_togo'] ?? '?' ?></td>
            <td><?= $statistics['mb_total'] ?? '?' ?></td>
        </tr>
        <?php if (!$statistics['gzip_mode']): ?>
        <tr>
            <td><strong>%</strong></td>
            <td><?= isset($statistics['pct_this']) ? number_format($statistics['pct_this'], 2) : '?' ?></td>
            <td><?= isset($statistics['pct_done']) ? number_format($statistics['pct_done'], 2) : '?' ?></td>
            <td><?= isset($statistics['pct_togo']) ? number_format($statistics['pct_togo'], 2) : '?' ?></td>
            <td>100.00</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Performance Section (Auto-Tuner) -->
<?php if (isset($autoTuner) && $autoTuner['enabled']): ?>
<div class="performance-section">
    <h3>Performance (Auto-Tuner)</h3>
    <div class="performance-grid">
        <div class="perf-box">
            <span class="perf-label">System</span>
            <span class="perf-value" id="perf-system"><?= htmlspecialchars($autoTuner['os']) ?></span>
        </div>
        <div class="perf-box">
            <span class="perf-label">RAM Available</span>
            <span class="perf-value" id="perf-ram"><?= htmlspecialchars($autoTuner['available_ram_formatted']) ?></span>
        </div>
        <div class="perf-box">
            <span class="perf-label">Batch Size</span>
            <span class="perf-value" id="perf-batch"><?= number_format($autoTuner['batch_size']) ?> (auto)</span>
        </div>
        <div class="perf-box">
            <span class="perf-label">Memory</span>
            <span class="perf-value" id="perf-memory"><?= $autoTuner['memory_percentage'] ?>%</span>
        </div>
        <div class="perf-box">
            <span class="perf-label">Speed</span>
            <span class="perf-value" id="perf-speed"><?= number_format($autoTuner['speed_lps'], 0) ?> l/s</span>
        </div>
    </div>
    <?php if (!empty($autoTuner['adjustment'])): ?>
    <div class="adjustment-notice" id="adjustment-notice">
        <?= htmlspecialchars($autoTuner['adjustment']) ?>
    </div>
    <?php else: ?>
    <div class="adjustment-notice" id="adjustment-notice" style="display:none;"></div>
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
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-primary">Back to File List</a>
</div>

<?php elseif (!$session->hasError()): ?>

    <?php if ($delay > 0): ?>
    <p class="text-center text-muted">
        Waiting <?= $delay ?>ms before next session...
    </p>
    <?php endif; ?>

    <noscript>
        <div class="alert alert-warning text-center">
            JavaScript is disabled. Click the link below to continue manually.<br><br>
            <a href="<?= $view->url($nextParams) ?>" class="btn btn-primary">
                Continue from line <?= number_format($nextParams['start']) ?>
            </a>
        </div>
    </noscript>

    <div class="text-center mt-3">
        <a href="<?= $view->e($scriptUri) ?>" class="btn btn-secondary">
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

<div class="text-center mt-3">
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-primary">
        Start Over
    </a>
    <span class="text-muted" style="margin-left: 15px;">(DROP old tables before restarting)</span>
</div>

<?php endif; ?>
