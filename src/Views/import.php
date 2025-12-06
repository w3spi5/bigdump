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
 */
?>

<?php if ($testMode): ?>
<div class="alert alert-warning">
    <strong>Test Mode</strong> - Queries are being parsed but NOT executed.
</div>
<?php endif; ?>

<?php if ($session->hasError()): ?>
<div class="alert alert-error">
    <strong>Import Error</strong><br>
    <pre style="white-space: pre-wrap; margin-top: 10px;"><?= $view->e($session->getError()) ?></pre>
</div>
<?php endif; ?>

<div class="text-center mb-3">
    <h3>Processing: <strong><?= $view->e($session->getFilename()) ?></strong></h3>
    <p class="text-muted">Starting from line: <?= number_format($session->getStartLine()) ?></p>
</div>

<?php if (!$statistics['gzip_mode'] && $statistics['pct_done'] !== null): ?>
<div class="progress-container mb-3">
    <div class="progress-bar" style="width: <?= $statistics['pct_done'] ?>%">
        <?= $statistics['pct_done'] ?>%
    </div>
</div>
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
        <div class="stat-value"><?= $statistics['pct_done'] ?>%</div>
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
            <td><?= $statistics['lines_togo'] !== null ? number_format($statistics['lines_togo']) : '?' ?></td>
            <td><?= $statistics['lines_total'] !== null ? number_format($statistics['lines_total']) : '?' ?></td>
        </tr>
        <tr>
            <td><strong>Queries</strong></td>
            <td><?= number_format($statistics['queries_this']) ?></td>
            <td><?= number_format($statistics['queries_done']) ?></td>
            <td><?= $statistics['queries_togo'] !== null ? number_format($statistics['queries_togo']) : '?' ?></td>
            <td><?= $statistics['queries_total'] !== null ? number_format($statistics['queries_total']) : '?' ?></td>
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
            <td><?= $statistics['pct_this'] ?? '?' ?></td>
            <td><?= $statistics['pct_done'] ?? '?' ?></td>
            <td><?= $statistics['pct_togo'] ?? '?' ?></td>
            <td>100</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

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
