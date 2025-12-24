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

<!-- Configuration for JavaScript modules -->
<script id="bigdump-config"
        data-db-configured="<?= json_encode($dbConfigured && $connectionInfo && $connectionInfo['success']) ?>"
        data-gzip-supported="<?= json_encode(function_exists('gzopen')) ?>">
</script>

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
    Please edit <code class="code">config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="alert alert-error">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="info-box">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-cyan-100 dark:text-cyan-200">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="info-box">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <form method="post" action="" style="display:inline">
            <input type="hidden" name="fn" value="<?= $view->e($predefinedFile) ?>">
            <button type="submit" class="btn btn-blue mt-3">Start Import</button>
        </form>
    </div>
<?php else: ?>

    <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Available Dump Files</h3>
        <button onclick="showHistory()" class="btn btn-sm btn-indigo">
            <svg class="icon w-4 h-4 mr-1 fill-current"><use href="assets/icons.svg#clock-rotate-left"></use></svg> History
        </button>
    </div>

    <!-- Empty state message (shown when no files) -->
    <div id="noFilesMessage" class="alert alert-warning <?= empty($files) ? '' : 'hidden' ?>">
        No dump files found in <code class="code"><?= $view->e($uploadDir) ?></code><br>
        Upload a .sql, .gz or .csv file using the form below, or via FTP.
    </div>

    <!-- Files table (always rendered, hidden when empty) -->
    <div id="filesTableContainer" class="<?= empty($files) ? 'hidden' : '' ?>">
        <table class="table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="fileTableBody">
                <?php foreach ($files as $file): ?>
                <tr data-filename="<?= $view->e($file['name']) ?>">
                    <td><strong><?= $view->e($file['name']) ?></strong></td>
                    <td><?= $view->formatBytes($file['size']) ?></td>
                    <td><?= $view->e($file['date']) ?></td>
                    <td>
                        <?php
                        $badgeClass = match($file['type']) {
                            'SQL' => 'badge badge-blue',
                            'GZip' => 'badge badge-purple',
                            'CSV' => 'badge badge-green',
                            default => 'badge badge-blue'
                        };
                        ?>
                        <span class="<?= $badgeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <button type="button"
                                        onclick="previewFile('<?= $view->escapeJs($file['name']) ?>')"
                                        class="btn btn-icon btn-purple"
                                        title="Preview SQL content">
                                    <svg class="icon w-4 h-4 fill-current"><use href="assets/icons.svg#eye"></use></svg>
                                </button>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="fn" value="<?= $view->e($file['name']) ?>">
                                    <button type="submit" class="btn btn-green">Import</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="btn btn-red"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr class="border-t border-gray-200 dark:border-gray-700 my-6">

    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Upload Dump Files</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="alert alert-warning">
            Upload disabled. Directory <code class="code"><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong> &bull;
            Allowed types: <strong>.sql, .gz, .csv</strong><br>
            For larger files, use FTP to upload directly to <code class="code"><?= $view->e($uploadDir) ?></code>
        </p>

        <div class="file-upload" id="fileUpload" data-max-file-size="<?= $uploadMaxSize ?>" data-upload-url="<?= $view->e($scriptUri) ?>">
            <!-- Dropzone -->
            <div class="dropzone" id="dropzone">
                <div class="file-upload__icon">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="text-gray-700 dark:text-gray-300 mb-2">
                    <strong>Click to upload</strong> or drag and drop
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    SQL, GZip or CSV files up to <?= $view->formatBytes($uploadMaxSize) ?>
                </div>
                <input type="file"
                       class="hidden"
                       id="fileInput"
                       accept=".sql,.gz,.csv"
                       multiple>
            </div>

            <!-- File List -->
            <div class="mt-4 space-y-2" id="fileList"></div>

            <!-- Actions -->
            <div class="mt-4 flex gap-3" id="uploadActions" style="display: none;">
                <button type="button" class="btn btn-blue" id="uploadBtn">
                    Upload All Files
                </button>
                <button type="button" class="btn btn-gray" id="clearBtn">
                    Clear All
                </button>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>

<!-- Loading Overlay for Import -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="card card-body text-center" style="padding: 2rem;">
        <div class="spinner spinner-lg mx-auto mb-4"></div>
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">Preparing import...</div>
        <div class="text-muted mt-2">Loading file</div>
    </div>
</div>

<!-- SQL Preview Modal -->
<div class="modal-overlay hidden" id="previewModal" onclick="closePreviewModal(event)">
    <div class="modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="previewModalTitle"
         aria-describedby="previewModalSubtitle"
         onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="previewModalTitle">SQL Preview</h3>
                <p class="modal-subtitle" id="previewModalSubtitle">Loading...</p>
            </div>
            <button onclick="closePreviewModal()" class="modal-close" aria-label="Close preview modal">
                <svg class="icon w-5 h-5 fill-current" aria-hidden="true"><use href="assets/icons.svg#xmark"></use></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <!-- Loading State -->
            <div id="previewLoading" class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="spinner spinner-md mx-auto mb-3"></div>
                    <div class="text-muted">Loading preview...</div>
                </div>
            </div>

            <!-- Error State -->
            <div id="previewError" class="hidden flex-1 flex items-center justify-center">
                <div class="text-center text-red-500">
                    <svg class="icon w-10 h-10 mx-auto mb-3 fill-current"><use href="assets/icons.svg#circle-exclamation"></use></svg>
                    <div id="previewErrorMessage">Error loading preview</div>
                </div>
            </div>

            <!-- Content -->
            <div id="previewContent" class="hidden flex-1 flex flex-col overflow-hidden">
                <!-- File Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="metric-box">
                        <span class="metric-label">Size</span>
                        <span class="metric-value" id="previewFileSize">-</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Type</span>
                        <span class="metric-value" id="previewFileType">-</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Total Lines</span>
                        <span class="metric-value" id="previewTotalLines">-</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Queries Found</span>
                        <span class="metric-value" id="previewQueriesCount">-</span>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700 mb-4">
                    <button onclick="switchPreviewTab('raw')" class="preview-tab px-4 py-2 text-sm font-medium border-b-2 transition-colors" id="tabRaw" data-active="true">
                        <svg class="icon w-4 h-4 mr-2 fill-current"><use href="assets/icons.svg#code"></use></svg>Raw Content
                    </button>
                    <button onclick="switchPreviewTab('queries')" class="preview-tab px-4 py-2 text-sm font-medium border-b-2 transition-colors" id="tabQueries" data-active="false">
                        <svg class="icon w-4 h-4 mr-2 fill-current"><use href="assets/icons.svg#database"></use></svg>Queries (<span id="tabQueriesCount">0</span>)
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="flex-1 overflow-auto">
                    <pre id="previewRaw" class="bg-gray-900 text-gray-100 p-4 rounded-lg text-sm font-mono overflow-x-auto whitespace-pre-wrap"></pre>
                    <div id="previewQueries" class="hidden space-y-3"></div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
            <button onclick="closePreviewModal()" class="btn btn-secondary">
                Close
            </button>
            <form method="post" action="" id="previewImportForm" style="display:inline">
                <input type="hidden" name="fn" id="previewImportFilename" value="">
                <button type="submit" class="btn btn-green">
                    <svg class="icon w-4 h-4 mr-2 fill-current"><use href="assets/icons.svg#play"></use></svg>Start Import
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Import History Modal -->
<div class="modal-overlay hidden" id="historyModal" onclick="closeHistoryModal(event)">
    <div class="modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="historyModalTitle"
         aria-describedby="historyModalSubtitle"
         onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="historyModalTitle">
                    <svg class="icon w-5 h-5 mr-2 fill-indigo-500" aria-hidden="true"><use href="assets/icons.svg#clock-rotate-left"></use></svg>Import History
                </h3>
                <p class="modal-subtitle" id="historyModalSubtitle">Recent import operations</p>
            </div>
            <button onclick="closeHistoryModal()" class="modal-close" aria-label="Close history modal">
                <svg class="icon w-5 h-5 fill-current" aria-hidden="true"><use href="assets/icons.svg#xmark"></use></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <!-- Loading State -->
            <div id="historyLoading" class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="spinner spinner-md mx-auto mb-3"></div>
                    <div class="text-muted">Loading history...</div>
                </div>
            </div>

            <!-- Content -->
            <div id="historyContent" class="hidden flex-1 flex flex-col overflow-hidden">
                <!-- Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="metric-box">
                        <span class="metric-label">Total Imports</span>
                        <span class="metric-value" id="histStatTotal">0</span>
                    </div>
                    <div class="metric-box" style="background: linear-gradient(135deg, rgba(34,197,94,0.1) 0%, rgba(34,197,94,0.05) 100%);">
                        <span class="metric-label" style="color: #16a34a;">Successful</span>
                        <span class="metric-value" style="color: #15803d;" id="histStatSuccess">0</span>
                    </div>
                    <div class="metric-box" style="background: linear-gradient(135deg, rgba(239,68,68,0.1) 0%, rgba(239,68,68,0.05) 100%);">
                        <span class="metric-label" style="color: #dc2626;">Failed</span>
                        <span class="metric-value" style="color: #b91c1c;" id="histStatFailed">0</span>
                    </div>
                    <div class="metric-box" style="background: linear-gradient(135deg, rgba(59,130,246,0.1) 0%, rgba(59,130,246,0.05) 100%);">
                        <span class="metric-label" style="color: #2563eb;">Total Queries</span>
                        <span class="metric-value" style="color: #1d4ed8;" id="histStatQueries">0</span>
                    </div>
                </div>

                <!-- History Table -->
                <div class="flex-1 overflow-auto">
                    <table class="table">
                        <thead class="sticky top-0">
                            <tr>
                                <th class="w-12"></th>
                                <th>Filename</th>
                                <th>Date</th>
                                <th>Stats</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer" style="justify-content: space-between;">
            <button onclick="clearHistory()" class="btn btn-danger-ghost">
                <svg class="icon w-4 h-4 mr-1 fill-current"><use href="assets/icons.svg#trash"></use></svg> Clear History
            </button>
            <button onclick="closeHistoryModal()" class="btn btn-secondary">
                Close
            </button>
        </div>
    </div>
</div>
