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
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
    <strong>Test Mode Enabled</strong> - Queries will be parsed but not executed.
</div>
<?php endif; ?>

<?php if (isset($uploadResult)): ?>
    <?php if ($uploadResult['success']): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-300 dark:border-green-700"><?= $view->e($uploadResult['message']) ?></div>
    <?php else: ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700"><?= $view->e($uploadResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($deleteResult)): ?>
    <?php if ($deleteResult['success']): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-300 dark:border-green-700"><?= $view->e($deleteResult['message']) ?></div>
    <?php else: ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700"><?= $view->e($deleteResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$dbConfigured): ?>
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700">
    <strong>Database not configured!</strong><br>
    Please edit <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono">config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="bg-cyan-600 dark:bg-cyan-800 text-white rounded-xl px-6 py-4 mb-6">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-cyan-100 dark:text-cyan-200">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="bg-cyan-600 dark:bg-cyan-800 text-white rounded-xl px-6 py-4 mb-6">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <form method="post" action="" style="display:inline">
            <input type="hidden" name="fn" value="<?= $view->e($predefinedFile) ?>">
            <button type="submit" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-blue-600 hover:bg-blue-700 hover:scale-105 hover:shadow-lg active:scale-95 text-white mt-3">Start Import</button>
        </form>
    </div>
<?php else: ?>

    <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Available Dump Files</h3>
        <button onclick="showHistory()" class="px-3 py-1.5 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer bg-indigo-500 hover:bg-indigo-600 hover:scale-105 active:scale-95 text-white">
            <i class="fa-solid fa-clock-rotate-left mr-1"></i> History
        </button>
    </div>

    <?php if (empty($files)): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
            No dump files found in <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code><br>
            Upload a .sql, .gz or .csv file using the form below, or via FTP.
        </div>
    <?php else: ?>
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
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
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
                                        class="px-3 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-purple-500 hover:bg-purple-600 hover:scale-105 hover:shadow-lg active:scale-95 text-white"
                                        title="Preview SQL content">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="fn" value="<?= $view->e($file['name']) ?>">
                                    <button type="submit" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-green-500 hover:bg-green-600 hover:scale-105 hover:shadow-lg active:scale-95 text-white">Import</button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-red-500 hover:bg-red-600 hover:scale-105 hover:shadow-lg active:scale-95 text-white"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr class="border-t border-gray-200 dark:border-gray-700 my-6">

    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Upload Dump Files</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
            Upload disabled. Directory <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-gray-500 dark:text-gray-400 mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong> &bull;
            Allowed types: <strong>.sql, .gz, .csv</strong><br>
            For larger files, use FTP to upload directly to <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code>
        </p>

        <div class="file-upload" id="fileUpload" data-max-file-size="<?= $uploadMaxSize ?>" data-upload-url="<?= $view->e($scriptUri) ?>">
            <!-- Dropzone -->
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:scale-[1.01] hover:shadow-lg transition-all duration-200 bg-gray-50 dark:bg-gray-800/50" id="dropzone">
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
                <button type="button" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-blue-600 hover:bg-blue-700 hover:scale-105 hover:shadow-lg active:scale-95 text-white" id="uploadBtn">
                    Upload All Files
                </button>
                <button type="button" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer inline-block text-center no-underline bg-gray-500 hover:bg-gray-600 hover:scale-105 hover:shadow-lg active:scale-95 text-white" id="clearBtn">
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
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <button onclick="closePreviewModal()" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200">
                Close
            </button>
            <form method="post" action="" id="previewImportForm" style="display:inline">
                <input type="hidden" name="fn" id="previewImportFilename" value="">
                <button type="submit" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer bg-green-500 hover:bg-green-600 hover:scale-105 hover:shadow-lg active:scale-95 text-white">
                    <i class="fa-solid fa-play mr-2"></i>Start Import
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// SQL Preview Functions - Using safe DOM methods
function previewFile(filename) {
    const modal = document.getElementById('previewModal');
    const loading = document.getElementById('previewLoading');
    const error = document.getElementById('previewError');
    const content = document.getElementById('previewContent');

    // Reset state
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    error.classList.add('hidden');
    content.classList.add('hidden');

    // Update title using textContent (safe)
    document.getElementById('previewModalTitle').textContent = filename;
    document.getElementById('previewModalSubtitle').textContent = 'Loading preview...';
    document.getElementById('previewImportFilename').value = filename;

    // Fetch preview
    fetch('?action=preview&fn=' + encodeURIComponent(filename))
        .then(response => response.json())
        .then(data => {
            loading.classList.add('hidden');

            if (data.error) {
                error.classList.remove('hidden');
                document.getElementById('previewErrorMessage').textContent = data.error;
                return;
            }

            // Update info using textContent (safe)
            document.getElementById('previewModalSubtitle').textContent = data.fileSizeFormatted + (data.isGzip ? ' (GZip compressed)' : '');
            document.getElementById('previewFileSize').textContent = data.fileSizeFormatted;
            document.getElementById('previewFileType').textContent = data.isGzip ? 'GZip' : 'SQL';
            document.getElementById('previewLinesCount').textContent = data.linesPreview;
            document.getElementById('previewQueriesCount').textContent = data.queriesPreview;
            document.getElementById('tabQueriesCount').textContent = data.queriesPreview;

            // Raw content - use textContent for safety
            document.getElementById('previewRaw').textContent = data.rawContent;

            // Queries list - build safely with DOM methods
            const queriesEl = document.getElementById('previewQueries');
            queriesEl.replaceChildren(); // Clear safely

            data.queries.forEach((query, index) => {
                const div = document.createElement('div');
                div.className = 'bg-gray-50 dark:bg-gray-700 rounded-lg p-4';

                // Header row
                const header = document.createElement('div');
                header.className = 'flex items-center justify-between mb-2';

                const label = document.createElement('span');
                label.className = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
                label.textContent = 'Query ' + (index + 1);

                const badge = document.createElement('span');
                badge.className = 'text-xs px-2 py-1 rounded ' + getQueryTypeClass(query);
                badge.textContent = getQueryType(query);

                header.appendChild(label);
                header.appendChild(badge);

                // Query content
                const pre = document.createElement('pre');
                pre.className = 'text-sm font-mono text-gray-800 dark:text-gray-200 overflow-x-auto whitespace-pre-wrap';
                pre.textContent = query; // Safe: textContent escapes HTML

                div.appendChild(header);
                div.appendChild(pre);
                queriesEl.appendChild(div);
            });

            content.classList.remove('hidden');
            switchPreviewTab('raw');
        })
        .catch(err => {
            loading.classList.add('hidden');
            error.classList.remove('hidden');
            document.getElementById('previewErrorMessage').textContent = 'Network error: ' + err.message;
        });
}

function closePreviewModal(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('previewModal').classList.add('hidden');
}

function switchPreviewTab(tab) {
    const rawTab = document.getElementById('tabRaw');
    const queriesTab = document.getElementById('tabQueries');
    const rawContent = document.getElementById('previewRaw');
    const queriesContent = document.getElementById('previewQueries');

    if (tab === 'raw') {
        rawTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        rawTab.classList.remove('border-transparent', 'text-gray-500');
        queriesTab.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        queriesTab.classList.add('border-transparent', 'text-gray-500');
        rawContent.classList.remove('hidden');
        queriesContent.classList.add('hidden');
    } else {
        queriesTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        queriesTab.classList.remove('border-transparent', 'text-gray-500');
        rawTab.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        rawTab.classList.add('border-transparent', 'text-gray-500');
        queriesContent.classList.remove('hidden');
        rawContent.classList.add('hidden');
    }
}

function getQueryType(query) {
    const q = query.trim().toUpperCase();
    if (q.startsWith('CREATE TABLE')) return 'CREATE TABLE';
    if (q.startsWith('CREATE DATABASE')) return 'CREATE DATABASE';
    if (q.startsWith('DROP TABLE')) return 'DROP TABLE';
    if (q.startsWith('INSERT')) return 'INSERT';
    if (q.startsWith('UPDATE')) return 'UPDATE';
    if (q.startsWith('DELETE')) return 'DELETE';
    if (q.startsWith('ALTER')) return 'ALTER';
    if (q.startsWith('SET')) return 'SET';
    if (q.startsWith('USE')) return 'USE';
    return 'SQL';
}

function getQueryTypeClass(query) {
    const type = getQueryType(query);
    switch (type) {
        case 'CREATE TABLE':
        case 'CREATE DATABASE':
            return 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200';
        case 'DROP TABLE':
        case 'DELETE':
            return 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200';
        case 'INSERT':
            return 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200';
        case 'UPDATE':
        case 'ALTER':
            return 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200';
        default:
            return 'bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200';
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreviewModal();
        closeHistoryModal();
    }
});

// Import History Functions
function showHistory() {
    const modal = document.getElementById('historyModal');
    const loading = document.getElementById('historyLoading');
    const content = document.getElementById('historyContent');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');

    fetch('?action=history&limit=20')
        .then(response => response.json())
        .then(data => {
            loading.classList.add('hidden');

            if (!data.success) {
                content.textContent = 'Error loading history';
                content.classList.remove('hidden');
                return;
            }

            // Update statistics
            const stats = data.statistics;
            document.getElementById('histStatTotal').textContent = stats.total_imports;
            document.getElementById('histStatSuccess').textContent = stats.successful_imports;
            document.getElementById('histStatFailed').textContent = stats.failed_imports;
            document.getElementById('histStatQueries').textContent = stats.total_queries.toLocaleString();

            // Build history table
            const tbody = document.getElementById('historyTableBody');
            tbody.replaceChildren();

            if (data.history.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.className = 'px-4 py-8 text-center text-gray-500 dark:text-gray-400';
                td.textContent = 'No import history yet';
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                data.history.forEach(entry => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150';

                    // Status icon
                    const tdStatus = document.createElement('td');
                    tdStatus.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-center';
                    const icon = document.createElement('i');
                    icon.className = entry.success
                        ? 'fa-solid fa-circle-check text-green-500'
                        : 'fa-solid fa-circle-xmark text-red-500';
                    tdStatus.appendChild(icon);

                    // Filename
                    const tdFile = document.createElement('td');
                    tdFile.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 font-medium';
                    tdFile.textContent = entry.filename;

                    // Date
                    const tdDate = document.createElement('td');
                    tdDate.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm';
                    tdDate.textContent = entry.datetime;

                    // Stats
                    const tdStats = document.createElement('td');
                    tdStats.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-sm';
                    tdStats.textContent = entry.queries_executed.toLocaleString() + ' queries / ' + entry.size_formatted;

                    // Result
                    const tdResult = document.createElement('td');
                    tdResult.className = 'px-4 py-3 border-b border-gray-200 dark:border-gray-700';
                    const badge = document.createElement('span');
                    badge.className = entry.success
                        ? 'px-2 py-1 rounded text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200'
                        : 'px-2 py-1 rounded text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200';
                    badge.textContent = entry.success ? 'Success' : 'Failed';
                    tdResult.appendChild(badge);

                    tr.appendChild(tdStatus);
                    tr.appendChild(tdFile);
                    tr.appendChild(tdDate);
                    tr.appendChild(tdStats);
                    tr.appendChild(tdResult);
                    tbody.appendChild(tr);
                });
            }

            content.classList.remove('hidden');
        })
        .catch(err => {
            loading.classList.add('hidden');
            content.textContent = 'Network error: ' + err.message;
            content.classList.remove('hidden');
        });
}

function closeHistoryModal(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('historyModal').classList.add('hidden');
}

function clearHistory() {
    if (!confirm('Are you sure you want to clear all import history?')) return;

    fetch('?action=history&do=clear')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showHistory(); // Refresh
            }
        });
}
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
        <div class="flex justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <button onclick="clearHistory()" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300">
                <i class="fa-solid fa-trash mr-1"></i> Clear History
            </button>
            <button onclick="closeHistoryModal()" class="px-4 py-2 rounded-md font-medium text-sm transition-all duration-150 cursor-pointer bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200">
                Close
            </button>
        </div>
    </div>
</div>
