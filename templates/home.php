<?php
/**
 * View: Home page
 *
 * Displays the list of available files and the upload form.
 *
 * @var \BigDump\Core\View $view
 * @var array $files List of files
 * @var bool $dbConfigured Database configured
 * @var array|null $connectionInfo Connection information
 * @var bool $uploadEnabled Upload enabled
 * @var int $uploadMaxSize Maximum upload size
 * @var string $uploadDir Upload directory
 * @var string $predefinedFile Predefined file
 * @var string $dbName Database name
 * @var string $dbServer Database server
 * @var bool $testMode Test mode
 */
?>

<?php if ($testMode): ?>
<div class="alert alert-warning">
    <strong>Test Mode Enabled</strong> - Queries will be parsed but not executed.
</div>
<?php endif; ?>

<?php if (isset($uploadResult)): ?>
    <?php if ($uploadResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($uploadResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($uploadResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($deleteResult)): ?>
    <?php if ($deleteResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($deleteResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($deleteResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$dbConfigured): ?>
<div class="alert alert-error">
    <strong>Database not configured!</strong><br>
    Please edit <code>config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="alert alert-error">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="alert alert-info">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-muted">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="alert alert-info">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <form method="post" action="" style="display:inline">
            <input type="hidden" name="fn" value="<?= $view->e($predefinedFile) ?>">
            <button type="submit" class="btn btn-primary mt-3">Start Import</button>
        </form>
    </div>
<?php else: ?>

    <h3 class="mb-3">Available Dump Files</h3>

    <?php if (empty($files)): ?>
        <div class="alert alert-warning">
            No dump files found in <code><?= $view->e($uploadDir) ?></code><br>
            Upload a .sql, .gz or .csv file using the form below, or via FTP.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr>
                    <td><strong><?= $view->e($file['name']) ?></strong></td>
                    <td><?= $view->formatBytes($file['size']) ?></td>
                    <td><?= $view->e($file['date']) ?></td>
                    <td>
                        <?php
                        $typeClass = match($file['type']) {
                            'SQL' => 'sql',
                            'GZip' => 'gz',
                            'CSV' => 'csv',
                            default => 'sql'
                        };
                        ?>
                        <span class="file-type file-type-<?= $typeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="fn" value="<?= $view->e($file['name']) ?>">
                                    <button type="submit" class="btn btn-success">Import</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr class="mt-3 mb-3" style="border: none; border-top: 1px solid #e2e8f0;">

    <h3 class="mb-3">Upload Dump Files</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="alert alert-warning">
            Upload disabled. Directory <code><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong> &bull;
            Allowed types: <strong>.sql, .gz, .csv</strong><br>
            For larger files, use FTP to upload directly to <code><?= $view->e($uploadDir) ?></code>
        </p>

        <div class="file-upload" id="fileUpload" data-max-file-size="<?= $uploadMaxSize ?>" data-upload-url="<?= $view->e($scriptUri) ?>">
            <!-- Dropzone -->
            <div class="file-upload__dropzone" id="dropzone">
                <div class="file-upload__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="file-upload__text">
                    <strong>Click to upload</strong> or drag and drop
                </div>
                <div class="file-upload__hint">
                    SQL, GZip or CSV files up to <?= $view->formatBytes($uploadMaxSize) ?>
                </div>
                <input type="file"
                       class="file-upload__input"
                       id="fileInput"
                       accept=".sql,.gz,.csv"
                       multiple>
            </div>

            <!-- File List -->
            <div class="file-upload__list" id="fileList"></div>

            <!-- Actions -->
            <div class="file-upload__actions" id="uploadActions" style="display: none;">
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    Upload All Files
                </button>
                <button type="button" class="btn btn-secondary" id="clearBtn">
                    Clear All
                </button>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>

<!-- Loading Overlay for Import -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <div class="loading-text">Preparing import...</div>
        <div class="loading-subtext">Loading file</div>
    </div>
</div>
