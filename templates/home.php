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
            <i class="fa-solid fa-clock-rotate-left mr-1"></i> History
        </button>
    </div>

    <!-- Empty state message (shown when no files) -->
    <div id="noFilesMessage" class="alert alert-warning <?= empty($files) ? '' : 'hidden' ?>">
        No dump files found in <code class="code"><?= $view->e($uploadDir) ?></code><br>
        Upload a .sql, .gz or .csv file using the form below, or via FTP.
    </div>

    <!-- Files table (always rendered, hidden when empty) -->
    <div id="filesTableContainer" class="<?= empty($files) ? 'hidden' : '' ?>">
        <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Filename</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Size</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="fileTableBody">
                <?php foreach ($files as $file): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150" data-filename="<?= $view->e($file['name']) ?>">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong><?= $view->e($file['name']) ?></strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $view->formatBytes($file['size']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $view->e($file['date']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100">
                        <?php
                        $typeClass = match($file['type']) {
                            'SQL' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200',
                            'GZip' => 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200',
                            'CSV' => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
                            default => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200'
                        };
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-medium <?= $typeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <button type="button"
                                        onclick="previewFile('<?= $view->escapeJs($file['name']) ?>')"
                                        class="btn btn-icon btn-purple"
                                        title="Preview SQL content">
                                    <i class="fa-solid fa-eye"></i>
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
<div class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden" id="loadingOverlay">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 text-center shadow-xl">
        <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">Preparing import...</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">Loading file</div>
    </div>
</div>

<!-- SQL Preview Modal -->
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden" id="previewModal" onclick="closePreviewModal(event)">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col mx-4" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" id="previewModalTitle">SQL Preview</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400" id="previewModalSubtitle">Loading...</p>
            </div>
            <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-hidden flex flex-col p-6">
            <!-- Loading State -->
            <div id="previewLoading" class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-3"></div>
                    <div class="text-gray-500 dark:text-gray-400">Loading preview...</div>
                </div>
            </div>

            <!-- Error State -->
            <div id="previewError" class="hidden flex-1 flex items-center justify-center">
                <div class="text-center text-red-500">
                    <i class="fa-solid fa-circle-exclamation text-4xl mb-3"></i>
                    <div id="previewErrorMessage">Error loading preview</div>
                </div>
            </div>

            <!-- Content -->
            <div id="previewContent" class="hidden flex-1 flex flex-col overflow-hidden">
                <!-- File Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Size</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100" id="previewFileSize">-</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Type</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100" id="previewFileType">-</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Lines Preview</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100" id="previewLinesCount">-</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Queries Found</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100" id="previewQueriesCount">-</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700 mb-4">
                    <button onclick="switchPreviewTab('raw')" class="preview-tab px-4 py-2 text-sm font-medium border-b-2 transition-colors" id="tabRaw" data-active="true">
                        <i class="fa-solid fa-code mr-2"></i>Raw Content
                    </button>
                    <button onclick="switchPreviewTab('queries')" class="preview-tab px-4 py-2 text-sm font-medium border-b-2 transition-colors" id="tabQueries" data-active="false">
                        <i class="fa-solid fa-database mr-2"></i>Queries (<span id="tabQueriesCount">0</span>)
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
                    <i class="fa-solid fa-play mr-2"></i>Start Import
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Escape key handler for modals -->
<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (typeof closePreviewModal === 'function') closePreviewModal();
        if (typeof closeHistoryModal === 'function') closeHistoryModal();
    }
});
</script>

<!-- Import History Modal -->
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden" id="historyModal" onclick="closeHistoryModal(event)">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col mx-4" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-indigo-500"></i>Import History
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Recent import operations</p>
            </div>
            <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-hidden flex flex-col p-6">
            <!-- Loading State -->
            <div id="historyLoading" class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mx-auto mb-3"></div>
                    <div class="text-gray-500 dark:text-gray-400">Loading history...</div>
                </div>
            </div>

            <!-- Content -->
            <div id="historyContent" class="hidden flex-1 flex flex-col overflow-hidden">
                <!-- Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Total Imports</div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100" id="histStatTotal">0</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-green-600 dark:text-green-400 mb-1">Successful</div>
                        <div class="font-semibold text-green-700 dark:text-green-300" id="histStatSuccess">0</div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-red-600 dark:text-red-400 mb-1">Failed</div>
                        <div class="font-semibold text-red-700 dark:text-red-300" id="histStatFailed">0</div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
                        <div class="text-xs uppercase tracking-wide text-blue-600 dark:text-blue-400 mb-1">Total Queries</div>
                        <div class="font-semibold text-blue-700 dark:text-blue-300" id="histStatQueries">0</div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="flex-1 overflow-auto">
                    <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600 w-12"></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Filename</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Stats</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Result</th>
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
                <i class="fa-solid fa-trash mr-1"></i> Clear History
            </button>
            <button onclick="closeHistoryModal()" class="btn btn-secondary">
                Close
            </button>
        </div>
    </div>
</div>
